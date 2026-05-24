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
use App\Exception\Handler\AppExceptionHandler;
use App\Exception\Handler\BusinessExceptionHandler;
use App\Exception\Handler\JwtExceptionHandler;
use App\Exception\Handler\ModeNotFoundHandler;
use App\Exception\Handler\NotFoundHttpExceptionHandler;
use App\Exception\Handler\UnauthorizedExceptionHandler;
use App\Exception\Handler\ValidationExceptionHandler;

return [
    'handler' => [
        'http' => [
            ModeNotFoundHandler::class,
            // 处理404异常
            NotFoundHttpExceptionHandler::class,
            // 处理业务异常
            BusinessExceptionHandler::class,
            // 处理未授权异常
            UnauthorizedExceptionHandler::class,
            // 处理验证器异常
            ValidationExceptionHandler::class,
            // 处理JWT异常
            JwtExceptionHandler::class,
            // 处理应用异常
            AppExceptionHandler::class,
        ],
    ],
];
