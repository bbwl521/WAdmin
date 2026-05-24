<?php

declare(strict_types=1);
/**
 * This file is part of WAdmin.
 *
 * @link     https://github.com/bbwl521/WAdmin
 * @document https://github.com/bbwl521/WAdmin
 * @contact  admin@wadmin.local
 * @license  https://github.com/bbwl521/WAdmin/blob/master/LICENSE
 */
use App\Http\Common\Middleware\InstallCheckMiddleware;
use App\Http\Common\Middleware\InstallRateLimitMiddleware;
use App\Http\Common\Middleware\PluginGuardMiddleware;
use Hyperf\Validation\Middleware\ValidationMiddleware;
use Mine\Support\Middleware\CorsMiddleware;
use Mine\Support\Middleware\RequestIdMiddleware;
use Mine\Support\Middleware\TranslationMiddleware;

return [
    'http' => [
        // 安装检测中间件（未安装时拦截到安装页面）
        InstallCheckMiddleware::class,
        // 安装接口限流中间件（防止滥用安装端点）
        InstallRateLimitMiddleware::class,
        // 请求ID中间件
        RequestIdMiddleware::class,
        // 多语言识别中间件
        TranslationMiddleware::class,
        // 跨域中间件，正式环境建议关闭。使用 Nginx 等代理服务器处理跨域问题。
        CorsMiddleware::class,
        // 验证器中间件,处理 formRequest 验证器
        ValidationMiddleware::class,
        // 插件守卫中间件（拦截已禁用插件的路由）
        PluginGuardMiddleware::class,
    ],
];
