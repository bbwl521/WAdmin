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

namespace App\Library\DataPermission\Scope;

use App\Http\CurrentUser;
use App\Library\DataPermission\Factory;
use Hyperf\Database\Model\Builder;
use Hyperf\Database\Model\Model;
use Hyperf\Database\Model\Scope;

class DataScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = CurrentUser::ctxUser();
        if (empty($user)) {
            return;
        }

        Factory::make()->build($builder->getQuery(), $user);
    }
}
