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

namespace App\Model\Casts;

use App\Model\Permission\Meta;
use Hyperf\Codec\Json;
use Hyperf\Contract\CastsAttributes;
use Hyperf\DbConnection\Model\Model;

class MetaCast implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes): Meta
    {
        return new Meta(empty($value) ? [] : Json::decode($value));
    }

    /**
     * @param Meta $value
     * @param Model $model
     */
    public function set($model, string $key, $value, array $attributes)
    {
        return Json::encode($value);
    }
}
