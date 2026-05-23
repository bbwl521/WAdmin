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

namespace App\Http\Common\Middleware;

use Hyperf\HttpMessage\Server\Response;
use Hyperf\HttpMessage\Stream\SwooleFileStream;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class InstallCheckMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $uri = $request->getUri()->getPath();
        // 安装相关路径和白名单路径直接放行
        $allowedPaths = [
            '/install',
            '/favicon.ico',
            '/admin/install',
        ];

        foreach ($allowedPaths as $path) {
            if (str_starts_with($uri, $path)) {
                return $handler->handle($request);
            }
        }

        // 检查是否已安装（通过 runtime/.install/install.lock 锁文件）
        $installed = file_exists(BASE_PATH . '/runtime/.install/install.lock');

        if (! $installed) {
            // 未安装，返回安装页面
            $stream = new SwooleFileStream(BASE_PATH . '/public/install.html');
            return (new Response())
                ->withHeader('Content-Type', 'text/html; charset=utf-8')
                ->withBody($stream);
        }

        return $handler->handle($request);
    }
}
