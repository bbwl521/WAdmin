<?php

declare(strict_types=1);

return [
    // 远程市场 API 地址
    'marketplace_url' => env('PLUGIN_MARKETPLACE_URL', 'https://marketplace.mineadmin.com'),

    // 市场 API 超时（秒）
    'marketplace_timeout' => (int) env('PLUGIN_MARKETPLACE_TIMEOUT', 30),

    // 是否允许安装未签名的插件
    'allow_unsigned' => (bool) env('PLUGIN_ALLOW_UNSIGNED', true),

    // 插件目录
    'plugins_dir' => BASE_PATH . '/plugins',

    // 临时文件目录
    'temp_dir' => BASE_PATH . '/runtime/plugin_temp',

    // 管理员角色 ID（插件菜单和权限关联的角色）
    'super_admin_role_id' => (int) env('PLUGIN_SUPER_ADMIN_ROLE_ID', 1),

    // 管理员角色名称（Casbin 规则中的 v0 角色标识）
    'super_admin_role_name' => env('PLUGIN_SUPER_ADMIN_ROLE_NAME', 'SuperAdmin'),

    // 插件守卫缓存 TTL（秒），禁用插件列表的本地缓存时间
    'guard_cache_ttl' => (int) env('PLUGIN_GUARD_CACHE_TTL', 60),
];
