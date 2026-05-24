<?php

declare(strict_types=1);
/**
 * This file is part of MineAdmin.
 *
 * @link     https://www.mineadmin.com
 * @document https://doc.mineadmin.com
 * @contact  root@imoi.cn
 * @license  https://github.com/mineadmin/MineAdmin/blob/master/LICENSE
 */

namespace App\Model;

use Carbon\Carbon;
use Hyperf\DbConnection\Model\Model as MineModel;

/**
 * @property int $id 主键
 * @property string $code 插件唯一标识
 * @property string $name 插件名称
 * @property string $version 当前版本号
 * @property string $source 来源: marketplace/local
 * @property int $status 状态: 1=已启用, 2=已禁用
 * @property array|null $config 插件私有配置
 * @property array|null $meta plugin.json 元数据快照
 * @property Carbon $created_at 安装时间
 * @property Carbon $updated_at 更新时间
 */
final class Plugin extends MineModel
{
    protected ?string $table = 'plugin';

    protected array $fillable = [
        'code', 'name', 'version', 'source', 'status', 'config', 'meta',
    ];

    protected array $casts = [
        'id' => 'integer',
        'status' => 'integer',
        'config' => 'json',
        'meta' => 'json',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
