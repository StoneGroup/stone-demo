<?php
return [
    'server' => [
        'user' => 'www-data', // 运行用户，一般和php-fpm运行用户相同
        'group' => 'www-data', // 运行组，同上
        'domain' => '/var/run/stone-server-fpm.sock', // unix domain socket地址，用来与nginx进程通信，推荐保持默认即可，如果系统不支持也可以是ip地址
        'port' => 9101, // 端口
        'handler' => 'App\Servers\Handler', // 请求处理器
        'pid' => '/var/run/stone.pid', // 进程文件
    ]
];
