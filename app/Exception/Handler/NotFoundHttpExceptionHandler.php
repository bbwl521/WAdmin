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

namespace App\Exception\Handler;

use App\Http\Common\Result;
use App\Http\Common\ResultCode;
use Hyperf\HttpMessage\Exception\NotFoundHttpException;
use HyperfContext\RequestContext;
use Hyperf\Di\Container;

final class NotFoundHttpExceptionHandler extends AbstractHandler
{
    public function handleResponse(\Throwable $throwable): Result
    {
        $this->stopPropagation();

        // 获取请求路径
        $path = 'unknown';
        try {
            $request = RequestContext::getRequest();
            if ($request) {
                $path = $request->getUri()->getPath();
            }
        } catch (\Throwable $e) {
            // 忽略获取请求的错误
        }

        // 记录到日志
        try {
            $container = Container::getInstance();
            $container->get(\Hyperf\Contract\StdoutLoggerInterface::class)->warning(
                sprintf('404 Not Found: %s', $path)
            );
        } catch (\Throwable $e) {
            // 忽略日志记录错误
        }

        return new Result(
            code: ResultCode::NOT_FOUND,
            message: sprintf('Route not found: %s', $path)
        );
    }

    public function isValid(\Throwable $throwable): bool
    {
        return $throwable instanceof NotFoundHttpException;
    }
}
