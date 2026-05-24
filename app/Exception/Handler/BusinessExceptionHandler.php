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

namespace App\Exception\Handler;

use App\Exception\BusinessException;
use App\Http\Common\Result;

final class BusinessExceptionHandler extends AbstractHandler
{
    /**
     * @param BusinessException $throwable
     */
    public function handleResponse(\Throwable $throwable): Result
    {
        $this->stopPropagation();
        return $throwable->getResponse();
    }

    public function isValid(\Throwable $throwable): bool
    {
        return $throwable instanceof BusinessException;
    }
}
