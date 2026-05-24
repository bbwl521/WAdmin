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

namespace App\Service\Logstash;

use App\Repository\Logstash\UserOperationLogRepository;
use App\Service\IService;

final class UserOperationLogService extends IService
{
    public function __construct(
        protected readonly UserOperationLogRepository $repository
    ) {}
}
