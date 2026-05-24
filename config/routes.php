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
use Hyperf\HttpMessage\Stream\SwooleStream;
use Hyperf\HttpServer\Router\Router;
use Swoole\Http\Response;

// 安装相关路由（首页、安装向导）
require_once BASE_PATH . '/config/install_routes.php';

// 加载插件 PSR-4 自动加载缓存（重启后恢复）
$pluginAutoloadCache = BASE_PATH . '/runtime/plugin_autoload.php';
if (file_exists($pluginAutoloadCache)) {
    $psr4Maps = require $pluginAutoloadCache;
    if (is_array($psr4Maps)) {
        $loader = require BASE_PATH . '/vendor/autoload.php';
        foreach ($psr4Maps as $namespace => $paths) {
            foreach ((array) $paths as $path) {
                $loader->addPsr4($namespace, $path, prepend: true);
            }
        }
    }
}

// 加载插件路由缓存（重启后自动恢复）
$pluginRouteCache = BASE_PATH . '/runtime/plugin_routes.php';
if (file_exists($pluginRouteCache)) {
    $allPluginRoutes = require $pluginRouteCache;
    if (is_array($allPluginRoutes)) {
        foreach ($allPluginRoutes as $routes) {
            if (is_array($routes)) {
                foreach ($routes as $route) {
                    if (count($route) >= 3) {
                        Router::addRoute(
                            [strtoupper((string) $route[0])],
                            (string) $route[1],
                            (string) $route[2],
                            (array) ($route['options'] ?? []),
                        );
                    }
                }
            }
        }
    }
}

Router::get('/favicon.ico', static function () {
    return '';
});

// 处理静态文件请求
Router::get('/uploads/{path:.*}', static function (string $path) {
    $filePath = BASE_PATH . '/storage/uploads/' . $path;
    $realPath = realpath($filePath);
    $uploadsDir = realpath(BASE_PATH . '/storage/uploads');
    if ($realPath === false || ! str_starts_with($realPath, $uploadsDir . '/')) {
        return new Hyperf\HttpMessage\Server\Response(new Response(), 404);
    }
    if (! file_exists($filePath)) {
        return new Hyperf\HttpMessage\Server\Response(new Response(), 404);
    }
    $mimeType = mime_content_type($filePath);
    $content = file_get_contents($filePath);
    $stream = new SwooleStream($content);
    return (new Hyperf\HttpMessage\Server\Response())
        ->withHeader('Content-Type', $mimeType)
        ->withHeader('Content-Length', (string) mb_strlen($content))
        ->withHeader('Cache-Control', 'public, max-age=31536000')
        ->withBody($stream);
});

// 插件市场仓库文件下载
Router::get('/plugin-repo/{path:.*}', static function (string $path) {
    $filePath = BASE_PATH . '/storage/plugins/repo/' . $path;
    $realPath = realpath($filePath);
    $repoDir = realpath(BASE_PATH . '/storage/plugins/repo');
    if ($realPath === false || ! str_starts_with($realPath, $repoDir . '/')) {
        return new Hyperf\HttpMessage\Server\Response(new Response(), 404);
    }
    if (! file_exists($filePath)) {
        return new Hyperf\HttpMessage\Server\Response(new Response(), 404);
    }
    $stream = new SwooleStream(file_get_contents($filePath));
    return (new Hyperf\HttpMessage\Server\Response())
        ->withHeader('Content-Type', 'application/zip')
        ->withHeader('Content-Disposition', 'attachment; filename="' . basename($path) . '"')
        ->withHeader('Content-Length', (string) filesize($filePath))
        ->withBody($stream);
});
