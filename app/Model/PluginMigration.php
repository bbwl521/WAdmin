<?php

declare(strict_types=1);

namespace App\Model;

use Carbon\Carbon;
use Hyperf\DbConnection\Model\Model as MineModel;

/**
 * @property int $id 主键
 * @property string $plugin_code 插件标识
 * @property string $migration 迁移文件名（不含 .php）
 * @property Carbon $created_at 执行时间
 */
final class PluginMigration extends MineModel
{
    protected ?string $table = 'plugin_migrations';

    public bool $timestamps = false;

    protected array $fillable = ['plugin_code', 'migration', 'created_at'];

    protected array $casts = [
        'id' => 'integer',
        'created_at' => 'datetime',
    ];
}
