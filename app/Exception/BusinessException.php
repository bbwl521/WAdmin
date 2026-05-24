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

namespace App\Exception;

use App\Http\Common\Result;
use App\Http\Common\ResultCode;

class BusinessException extends \RuntimeException
{
    private Result $response;

    public function __construct(ResultCode $code = ResultCode::FAIL, ?string $message = null, mixed $data = [])
    {
        parent::__construct($message ?? $code->getMessage());
        $this->response = new Result($code, $message, $data);
    }

    public function getResponse(): Result
    {
        return $this->response;
    }
}
