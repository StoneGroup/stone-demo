<?php namespace App\Servers;

use Stone\Contracts\RequestHandler;
use Response;

class Handler implements RequestHandler
{
    public function process()
    {
        return Response::make('hello, stone server!');
    }

    public function onWorkerStart()
    {

    }

    public function handleException($e)
    {

    }
}
