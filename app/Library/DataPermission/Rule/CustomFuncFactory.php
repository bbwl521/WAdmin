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

namespace App\Library\DataPermission\Rule;

class CustomFuncFactory
{
    /**
     * @var array<string,\Closure>
     */
    private static array $customFunc = [];

    public static function registerCustomFunc(string $name, \Closure $func): void
    {
        self::$customFunc[$name] = $func;
    }

    public static function getCustomFunc(string $name): \Closure
    {
        if (isset(self::$customFunc[$name])) {
            return self::$customFunc[$name];
        }
        throw new \RuntimeException('Custom func not found');
    }
}
