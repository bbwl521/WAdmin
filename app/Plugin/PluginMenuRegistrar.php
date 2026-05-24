<?php

declare(strict_types=1);

namespace App\Plugin;

use Hyperf\Contract\ConfigInterface;
use Hyperf\DbConnection\Db;
use Psr\Log\LoggerInterface;

/**
 * 插件菜单与权限注册器.
 *
 * 从 plugin.json 读取 menus 和 permissions 声明，
 * 自动写入菜单表、Casbin 规则表以及管理员角色关联。
 * 管理员角色可通过 config/autoload/plugin.php 中的 super_admin_role_id 和 super_admin_role_name 配置。
 */
final class PluginMenuRegistrar
{
    private int $superAdminRoleId;
    private string $superAdminRoleName;

    public function __construct(
        private readonly LoggerInterface $logger,
        ConfigInterface $config,
    ) {
        $this->superAdminRoleId = (int) $config->get('plugin.super_admin_role_id', 1);
        $this->superAdminRoleName = (string) $config->get('plugin.super_admin_role_name', 'SuperAdmin');
    }

    /**
     * 注册插件菜单和权限.
     *
     * @param array<int, array<string, mixed>> $menus 菜单树
     * @param array<int, array<string, string>> $permissions 权限列表 [{name, display_name}]
     */
    public function register(array $menus, array $permissions): void
    {
        Db::transaction(function () use ($menus, $permissions): void {
            if ($menus !== []) {
                $this->insertMenus($menus, parentId: 0);
            }

            if ($permissions !== []) {
                $this->insertPermissions($permissions);
            }
        });

        $this->logger->info(sprintf(
            '[PluginMenu] 已注册 %d 个菜单, %d 个权限',
            $this->countMenus($menus),
            count($permissions),
        ));
    }

    /**
     * 移除插件注册的所有菜单和权限.
     *
     * @param array<int, string> $names 权限标识和菜单名称列表
     */
    public function unregister(array $names): void
    {
        if ($names === []) {
            return;
        }

        Db::transaction(function () use ($names): void {
            $placeholders = implode(',', array_fill(0, count($names), '?'));

            // 删除 Casbin 规则
            Db::delete("DELETE FROM rules WHERE v1 IN ({$placeholders})", $names);

            // 删除菜单（含子菜单）
            foreach ($names as $name) {
                Db::delete('DELETE FROM menu WHERE name = ? OR name LIKE ?', [$name, $name . ':%']);
            }

            // 清理孤立关联
            Db::delete('DELETE FROM role_belongs_menu WHERE menu_id NOT IN (SELECT id FROM menu)');
            Db::delete('DELETE FROM rules WHERE v1 NOT IN (SELECT name FROM menu WHERE name IS NOT NULL)');
        });

        $this->logger->info(sprintf('[PluginMenu] 已移除 %d 个菜单/权限', count($names)));
    }

    /**
     * 递归插入菜单树.
     *
     * @param array<int, array<string, mixed>> $menus
     */
    private function insertMenus(array $menus, int $parentId): void
    {
        foreach ($menus as $menu) {
            $menuId = Db::table('menu')->insertGetId([
                'parent_id' => $parentId,
                'name' => $menu['name'],
                'meta' => json_encode($menu['meta'] ?? [], JSON_UNESCAPED_UNICODE),
                'path' => $menu['path'] ?? '',
                'component' => $menu['component'] ?? '',
                'redirect' => $menu['redirect'] ?? '',
                'status' => 1,
                'sort' => $menu['sort'] ?? 0,
                'created_by' => 0,
                'updated_by' => 0,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
                'remark' => $menu['remark'] ?? '',
            ]);

            // 关联到配置的管理员角色
            Db::table('role_belongs_menu')->insert([
                'role_id' => $this->superAdminRoleId,
                'menu_id' => $menuId,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            // 递归处理子菜单
            if (! empty($menu['children'])) {
                $this->insertMenus($menu['children'], $menuId);
            }
        }
    }

    /**
     * 批量插入 Casbin 权限规则（关联到配置的管理员角色）.
     *
     * @param array<int, array{name:string, display_name?:string}> $permissions
     */
    private function insertPermissions(array $permissions): void
    {
        $now = date('Y-m-d H:i:s');

        foreach ($permissions as $permission) {
            $name = $permission['name'];

            Db::table('rules')->insert([
                'ptype' => 'p',
                'v0' => $this->superAdminRoleName,
                'v1' => $name,
                'v2' => 'allow',
                'v3' => null,
                'v4' => null,
                'v5' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function countMenus(array $menus): int
    {
        $count = 0;
        foreach ($menus as $menu) {
            ++$count;
            if (! empty($menu['children'])) {
                $count += $this->countMenus($menu['children']);
            }
        }

        return $count;
    }
}
