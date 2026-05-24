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

namespace App\Service\Permission;

use App\Exception\BusinessException;
use App\Http\Common\ResultCode;
use App\Model\Permission\Position;
use App\Repository\Permission\PositionRepository;
use App\Service\IService;

/**
 * @extends IService<Position>
 */
class PositionService extends IService
{
    public function __construct(
        protected readonly PositionRepository $repository
    ) {}

    public function batchDataPermission(int $id, array $policy): void
    {
        $entity = $this->repository->findById($id);
        if ($entity === null) {
            throw new BusinessException(ResultCode::NOT_FOUND);
        }
        $policyEntity = $entity->policy()->first();
        if (empty($policyEntity)) {
            $entity->policy()->create($policy);
        } else {
            $policyEntity->update($policy);
        }
    }
}
