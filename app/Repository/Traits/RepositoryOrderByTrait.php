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

namespace App\Repository\Traits;

use Hyperf\Database\Model\Builder;

trait RepositoryOrderByTrait
{
    public function handleOrderBy(Builder $query, $params): Builder
    {
        if ($this->enablePageOrderBy()) {
            $orderByField = $params[$this->getOrderByParamName()] ?? $query->getModel()->getKeyName();
            $orderByDirection = $params[$this->getOrderByDirectionParamName()] ?? 'desc';
            $query->orderBy($orderByField, $orderByDirection);
        }
        return $query;
    }

    protected function bootRepositoryOrderByTrait(Builder $query, array $params): void
    {
        $this->handleOrderBy($query, $params);
    }

    protected function getOrderByParamName(): string
    {
        return 'order_by';
    }

    protected function getOrderByDirectionParamName(): string
    {
        return 'order_by_direction';
    }

    protected function enablePageOrderBy(): bool
    {
        return true;
    }
}
