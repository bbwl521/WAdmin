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

namespace App\Library\DataPermission;

enum ScopeType: int
{
    // 只根据部门过滤
    case DEPT = 1;

    // 只根据创建人过滤
    case CREATED_BY = 2;

    // 根据部门 and 创建人过滤
    case DEPT_CREATED_BY = 3;

    // 根据部门 or 创建人过滤
    case DEPT_OR_CREATED_BY = 4;
}
