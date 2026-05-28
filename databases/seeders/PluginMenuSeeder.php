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

namespace Database\Seeders;

use Hyperf\Database\Seeders\Seeder;
use Hyperf\DbConnection\Db;

class PluginMenuSeeder extends Seeder
{
    /**
     * Seed plugin management menu entries.
     */
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        // 插件管理菜单数据
        Db::table('menu')->insertOrIgnore([
            'id' => 50,
            'parent_id' => 0,
            'name' => 'plugin',
            'meta' => json_encode([
                'title' => '插件管理',
                'icon' => 'ri:plug-line',
                'type' => 'M',
                'hidden' => 0,
            ]),
            'path' => '/plugin',
            'component' => '',
            'redirect' => '',
            'status' => 1,
            'sort' => 99,
            'created_by' => 0,
            'updated_by' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'remark' => '插件市场与已安装插件管理',
        ]);

        // 插件市场子菜单
        Db::table('menu')->insertOrIgnore([
            'id' => 51,
            'parent_id' => 50,
            'name' => 'plugin:marketplace',
            'meta' => json_encode([
                'title' => '插件市场',
                'icon' => 'ri:store-2-line',
                'type' => 'M',
                'hidden' => 0,
                'componentPath' => 'modules/',
                'componentSuffix' => '.vue',
            ]),
            'path' => '/plugin/marketplace',
            'component' => 'base/views/plugin/marketplace/index',
            'redirect' => '',
            'status' => 1,
            'sort' => 0,
            'created_by' => 0,
            'updated_by' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'remark' => '浏览和安装插件',
        ]);

        // 市场按钮
        Db::table('menu')->insertOrIgnore([
            'id' => 52,
            'parent_id' => 51,
            'name' => 'plugin:marketplace:index',
            'meta' => json_encode([
                'title' => '市场列表',
                'type' => 'B',
            ]),
            'path' => '',
            'component' => '',
            'redirect' => '',
            'status' => 1,
            'sort' => 0,
            'created_by' => 0,
            'updated_by' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'remark' => '',
        ]);

        Db::table('menu')->insertOrIgnore([
            'id' => 53,
            'parent_id' => 51,
            'name' => 'plugin:marketplace:install',
            'meta' => json_encode([
                'title' => '安装插件',
                'type' => 'B',
            ]),
            'path' => '',
            'component' => '',
            'redirect' => '',
            'status' => 1,
            'sort' => 0,
            'created_by' => 0,
            'updated_by' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'remark' => '',
        ]);

        // 已安装插件子菜单
        Db::table('menu')->insertOrIgnore([
            'id' => 54,
            'parent_id' => 50,
            'name' => 'plugin:installed',
            'meta' => json_encode([
                'title' => '已安装插件',
                'icon' => 'ri:list-check-3',
                'type' => 'M',
                'hidden' => 0,
                'componentPath' => 'modules/',
                'componentSuffix' => '.vue',
            ]),
            'path' => '/plugin/installed',
            'component' => 'base/views/plugin/installed/index',
            'redirect' => '',
            'status' => 1,
            'sort' => 1,
            'created_by' => 0,
            'updated_by' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'remark' => '管理已安装的插件',
        ]);

        // 已安装按钮
        Db::table('menu')->insertOrIgnore([
            'id' => 55,
            'parent_id' => 54,
            'name' => 'plugin:installed:index',
            'meta' => json_encode([
                'title' => '已安装列表',
                'type' => 'B',
            ]),
            'path' => '',
            'component' => '',
            'redirect' => '',
            'status' => 1,
            'sort' => 0,
            'created_by' => 0,
            'updated_by' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'remark' => '',
        ]);

        Db::table('menu')->insertOrIgnore([
            'id' => 56,
            'parent_id' => 54,
            'name' => 'plugin:installed:enable',
            'meta' => json_encode([
                'title' => '启用插件',
                'type' => 'B',
            ]),
            'path' => '',
            'component' => '',
            'redirect' => '',
            'status' => 1,
            'sort' => 0,
            'created_by' => 0,
            'updated_by' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'remark' => '',
        ]);

        Db::table('menu')->insertOrIgnore([
            'id' => 57,
            'parent_id' => 54,
            'name' => 'plugin:installed:disable',
            'meta' => json_encode([
                'title' => '禁用插件',
                'type' => 'B',
            ]),
            'path' => '',
            'component' => '',
            'redirect' => '',
            'status' => 1,
            'sort' => 0,
            'created_by' => 0,
            'updated_by' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'remark' => '',
        ]);

        Db::table('menu')->insertOrIgnore([
            'id' => 58,
            'parent_id' => 54,
            'name' => 'plugin:installed:delete',
            'meta' => json_encode([
                'title' => '卸载插件',
                'type' => 'B',
            ]),
            'path' => '',
            'component' => '',
            'redirect' => '',
            'status' => 1,
            'sort' => 0,
            'created_by' => 0,
            'updated_by' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'remark' => '',
        ]);
    }
}
