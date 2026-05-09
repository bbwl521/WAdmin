<?php

declare(strict_types=1);
/**
 * This file is part of MineAdmin.
 *
 * @link     https://www.mineadmin.com
 * @document https://doc.mineadmin.com
 * @contact  root@imoi.cn
 * @license  https://github.com/mineadmin/MineAdmin/blob/master/LICENSE
 */
use Hyperf\HttpMessage\Stream\SwooleFileStream;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Hyperf\HttpServer\Router\Router;

Router::get('/', static function () {
    // Check if system is installed
    $envFile = BASE_PATH . '/.env';
    $installed = false;

    if (file_exists($envFile)) {
        $content = file_get_contents($envFile);
        $installed = $content !== false && str_contains($content, 'JWT_SECRET');
    }

    if (! $installed) {
        $stream = new SwooleFileStream(BASE_PATH . '/public/install.html');
        return (new \Hyperf\HttpMessage\Server\Response())
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withBody($stream);
    }

    return 'welcome use mineAdmin';
});

Router::get('/install', static function () {
    $stream = new SwooleFileStream(BASE_PATH . '/public/install.html');
    return (new \Hyperf\HttpMessage\Server\Response())
        ->withHeader('Content-Type', 'text/html; charset=utf-8')
        ->withBody($stream);
});

Router::get('/favicon.ico', static function () {
    return '';
});

// 处理静态文件请求
Router::get('/uploads/{path:.*}', static function (string $path) {
    $filePath = BASE_PATH . '/storage/uploads/' . $path;

    // 安全检查：确保文件在 uploads 目录下
    $realPath = realpath($filePath);
    $uploadsDir = realpath(BASE_PATH . '/storage/uploads');

    if ($realPath === false || strpos($realPath, $uploadsDir . '/') !== 0) {
        return new \Hyperf\HttpMessage\Server\Response(new \Swoole\Http\Response(), 404);
    }

    if (! file_exists($filePath)) {
        return new \Hyperf\HttpMessage\Server\Response(new \Swoole\Http\Response(), 404);
    }

    $mimeType = mime_content_type($filePath);
    $content = file_get_contents($filePath);
    $stream = new SwooleStream($content);

    return (new \Hyperf\HttpMessage\Server\Response())
        ->withHeader('Content-Type', $mimeType)
        ->withHeader('Content-Length', (string) strlen($content))
        ->withHeader('Cache-Control', 'public, max-age=31536000')
        ->withBody($stream);
});
