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

trait BootTrait
{
    protected function startBoot(...$params): void
    {
        $traits = class_uses_recursive(static::class);
        foreach ($traits as $trait) {
            $method = 'boot' . class_basename($trait);
            if (method_exists($this, $method)) {
                $this->{$method}(...$params);
            }
        }
    }
}
