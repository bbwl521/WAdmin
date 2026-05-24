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

use Hyperf\DbConnection\Model\Model;

/**
 * @internal
 * @mixin Model
 */
trait DataScopes
{
    public static function bootDataScopes(): void
    {
        static::addGlobalScope(new DataScope());
    }
}
