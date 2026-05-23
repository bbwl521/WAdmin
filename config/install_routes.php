<?php

declare(strict_types=1);

use Hyperf\HttpMessage\Stream\SwooleFileStream;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Hyperf\HttpServer\Router\Router;

Router::get('/', static function () {
    $installed = file_exists(BASE_PATH . '/runtime/.install/install.lock');

    if (! $installed) {
        $stream = new SwooleFileStream(BASE_PATH . '/public/install.html');
        return (new \Hyperf\HttpMessage\Server\Response())
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withBody($stream);
    }

    return 'welcome use mineAdmin';
});

Router::get('/install', static function () {
    $lockFile = BASE_PATH . '/runtime/.install/install.lock';

    // 已安装，重定向到首页
    if (file_exists($lockFile)) {
        return (new \Hyperf\HttpMessage\Server\Response())
            ->withStatus(302)
            ->withHeader('Location', '/');
    }

    // 未安装，显示安装向导页面
    $stream = new SwooleFileStream(BASE_PATH . '/public/install.html');
    return (new \Hyperf\HttpMessage\Server\Response())
        ->withHeader('Content-Type', 'text/html; charset=utf-8')
        ->withBody($stream);
});
