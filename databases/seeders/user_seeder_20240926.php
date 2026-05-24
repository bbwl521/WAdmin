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

use App\Model\Permission\Role;
use App\Model\Permission\User;
use Hyperf\Database\Seeders\Seeder;

class user_seeder_20240926 extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::truncate();
        Role::truncate();
        $entity = User::create([
            'username' => 'admin',
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
        $role = Role::create([
            'name' => '超级管理员',
            'code' => 'SuperAdmin',
        ]);
        $entity->roles()->sync($role);
    }
}
