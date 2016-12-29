<?php namespace App\Servers;

use Stone\Contracts\RequestHandler;
use Symfony\Component\HttpFoundation\Cookie as HttpCookie;
use App;
use Response;
use Request;
use DB;
use Cookie;
use Crypt;
use Log;

use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use FastRoute\simpleDispatcher;
use App\Servers\ServerHandlerException;

class ApiHandler implements RequestHandler
{
    /**
     * 路由分配器
     *
     * @var FastRoute\simpleDispatcher
     */
    private $dispatcher;

    /**
     * 不需要加密的cookie名称
     *
     * @var array
     */
    private $notEncryptCookies = ['robot_key'];

    /**
     * process
     * 处理一个请求
     *
     * @return void
     */
    public function process()
    {
        // 创建request对象
        $request = $this->createRequest();

        try {
            list($handler, $vars) = $this->dispatch($request);
            list($class, $method) = explode('@', $handler);
            $response = call_user_func_array([app($class), $method], $vars);
        } catch (ServerHandlerException $e) {
            $response = Response::make(['code' => $e->getCode(), 'message' => $e->getMessage()]);
        } catch (Exception $e) {
            Log::error($e);
            $response = Response::json(['code' => -1, 'message' => '系统繁忙']);
        }

        $this->flushCookieToResponse($response);
        $this->terminal();

        return $response;
    }

    /**
     * createRequest
     * 基于全局变量创建请求
     *
     * @return Request
     */
    protected function createRequest()
    {
        $request = Request::createFromGlobals();
        app()->instance('request', $request);

        return $request;
    }

    /**
     * dispatch
     *
     *
     * @param Request $request
     * @return array
     */
    protected function dispatch($request)
    {
        $httpMethod = $request->server('REQUEST_METHOD');
        $uri = $request->server('REQUEST_URI');

        // Strip query string (?foo=bar) and decode URI
        if (false !== $pos = strpos($uri, '?')) {
            $uri = substr($uri, 0, $pos);
        }
        $uri = rawurldecode($uri);

        $routeInfo = $this->dispatcher->dispatch($httpMethod, $uri);

        switch ($routeInfo[0]) {
            case Dispatcher::NOT_FOUND:
                throw new ServerHandlerException('页面不存在', 404);
            case Dispatcher::METHOD_NOT_ALLOWED:
                $allowedMethods = $routeInfo[1];
                throw new ServerHandlerException('没有权限', 401);
            case Dispatcher::FOUND:
                $handler = $routeInfo[1];
                $vars = $routeInfo[2];
                break;
        }

        if (!strpos($handler, '@')) {
            throw new ServerHandlerException('页面不存在', 404);
        }

        return [$handler, $vars];
    }

    /**
     * flushCookieToResponse
     * 将Cookie队列中的cookie保存到Response中并清空
     * 主要解决cli模式下， setcookie函数无法使用的问题，同时保持对fpm模式的兼容
     *
     * @param Response $response
     * @return void
     */
    protected function flushCookieToResponse($response)
    {
        $cookies = Cookie::getQueuedCookies();
        $isCliMode = php_sapi_name() == 'cli';

        if (empty($cookies)) {
            return;
        }

        foreach ($cookies as $key => $cookie) {

            // cookie需要加密
            if (!in_array($key, $this->notEncryptCookies)) {
                // 非cli模式， laravel4中间件会自动加密
                if (!$isCliMode) {
                    continue;
                }

                // cli模式下， 手动加密, 并删除cookie
                $response->headers->setCookie($this->encryptCookie($cookie));
                Cookie::unqueue($key);
                continue;
            }

            // cookie不需要加密
            if (!$isCliMode) {
                setcookie($cookie->getName(),
                          $cookie->getValue(),
                          $cookie->getExpiresTime(),
                          $cookie->getPath(),
                          $cookie->getDomain(),
                          $cookie->isSecure(),
                          $cookie->isHttpOnly()
                          );
            } else {
                $response->headers->setCookie($cookie);
            }

            Cookie::unqueue($key);
        }
    }

    /**
     * encryptCookie
     * 加密一个已有cookie
     *
     * @param Cookie $c
     * @return Cookie
     */
    public function encryptCookie(HttpCookie $c)
    {
        $value = Crypt::encrypt($c->getValue());

		return new HttpCookie(
			$c->getName(), $value, $c->getExpiresTime(), $c->getPath(),
			$c->getDomain(), $c->isSecure(), $c->isHttpOnly()
		);
    }

    /**
     * onWorkerStart
     * 做一些worker进程初始化时的工作
     *
     * @return void
     */
    public function onWorkerStart()
    {
        $this->dispatcher = \FastRoute\simpleDispatcher(function(RouteCollector $router) {
            require base_path() . '/routes/server.php';
        });
    }

    /**
     * terminal
     * 请求结束扫尾工作
     *
     * @return void
     */
    protected function terminal()
    {
        $connections = DB::getConnections();

        if (!empty($connections)) {
            foreach ($connections as $name => $connection) {
                $connection->flushQueryLog();
                DB::purge($name);
            }
        }
    }

    public function handleException($e) {
    }
}

