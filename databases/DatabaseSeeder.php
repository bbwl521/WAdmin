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
namespace Database;

use Database\Seeders\MenuSeeder20240926;
use Database\Seeders\UserDept20250310;
use Hyperf\Database\Seeders\Seeder;
use Hyperf\DbConnection\Db;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(?string $adminUsername = null, ?string $adminPassword = null): void
    {
        $this->seedAdminUser($adminUsername, $adminPassword);
        $this->seedMenus();
        $this->seedRoles();
        $this->seedRolePermissions();
    }

    /**
     * Seed admin user.
     */
    public function seedAdminUser(?string $username = null, ?string $password = null): void
    {
        $table = 'user';
        Db::table($table)->truncate();

        // 使用传入的账号密码，或使用默认值
        $username = $username ?: 'admin';
        $password = $password ?: '123456';

        // 创建 admin 用户
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        Db::table($table)->insert([
            'id' => 1,
            'username' => $username,
            'password' => $hashedPassword,
            'user_type' => '100',
            'nickname' => '创始人',
            'email' => 'admin@adminmine.com',
            'phone' => '16858888988',
            'signed' => '广阔天地，大有所为',
            'created_by' => 0,
            'updated_by' => 0,
            'status' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Seed menus.
     */
    private function seedMenus(): void
    {
        // 直接实例化并调用 seeder（Hyperf 的 Seeder 没有 call 方法）
        (new \Database\Seeders\MenuSeeder20240926())->run();
        (new \Database\Seeders\UserDept20250310())->run();
    }

    /**
     * Seed roles.
     */
    private function seedRoles(): void
    {
        $table = 'role';
        Db::table($table)->truncate();

        Db::table($table)->insert([
            'id' => 1,
            'name' => '超级管理员',
            'code' => 'SuperAdmin',
            'status' => 1,
            'sort' => 1,
            'created_by' => 0,
            'updated_by' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'remark' => '系统超级管理员，拥有所有权限',
        ]);
    }

    /**
     * Seed role permissions (user-role and role-menu).
     */
    private function seedRolePermissions(): void
    {
        $userBelongsRoleTable = 'user_belongs_role';
        $roleBelongsMenuTable = 'role_belongs_menu';

        Db::table($userBelongsRoleTable)->truncate();
        Db::table($roleBelongsMenuTable)->truncate();

        // 关联 admin 用户到 SuperAdmin 角色
        Db::table($userBelongsRoleTable)->insert([
            'user_id' => 1,
            'role_id' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // 获取所有菜单 ID
        $menuIds = Db::table('menu')->pluck('id')->toArray();

        // 关联 SuperAdmin 角色到所有菜单
        foreach ($menuIds as $menuId) {
            Db::table($roleBelongsMenuTable)->insert([
                'role_id' => 1,
                'menu_id' => $menuId,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        // 添加 Casbin 规则
        $rulesTable = config('permission.database.table', 'rules');
        Db::table($rulesTable)->truncate();
        Db::table($rulesTable)->insert([
            'v0' => 'admin',
            'v1' => 'SuperAdmin',
            'ptype' => 'g',
        ]);
    }
}
