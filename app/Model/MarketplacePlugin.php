<?php

declare(strict_types=1);
/**
 * This file is part of WAdmin.
 */

namespace App\Model;

use Carbon\Carbon;
use Hyperf\DbConnection\Model\Model as MineModel;

/**
 * @property int $id 主键
 * @property string $code 插件唯一标识
 * @property string $name 插件名称
 * @property string $version 版本号
 * @property string $description 描述
 * @property string $author 作者
 * @property string $category 分类
 * @property string $icon 图标
 * @property string $download_url 下载地址
 * @property int $downloads 下载次数
 * @property int $status 状态: 1=上架 2=下架
 * @property array|null $meta 元数据
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class MarketplacePlugin extends MineModel
{
    protected ?string $table = 'marketplace_plugin';

    protected array $fillable = [
        'code', 'name', 'version', 'description', 'author',
        'category', 'icon', 'download_url', 'downloads', 'status', 'meta',
    ];

    protected array $casts = [
        'id' => 'integer',
        'downloads' => 'integer',
        'status' => 'integer',
        'meta' => 'json',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
