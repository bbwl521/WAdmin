# 插件开发指南

WAdmin 提供了灵活的热插拔式插件架构，允许以插件形式扩展系统功能。

## 插件目录结构

```
plugins/my-plugin/
├── Plugin.php                  # 插件入口类
├── ConfigProvider.php          # 配置提供者
├── composer.json               # 插件依赖
├── src/
│   ├── Controller/             # 控制器
│   ├── Service/                # 服务
│   ├── Model/                  # 模型
│   └── Middleware/             # 中间件
├── databases/
│   └── migrations/             # 数据库迁移
└── routes/
    └── web.php                 # 路由配置
```

## 插件入口类

```php
<?php

namespace Plugin\MyPlugin;

use App\Plugin\AbstractPlugin;

class Plugin extends AbstractPlugin
{
    public function info(): array
    {
        return [
            'name'        => 'my-plugin',
            'version'     => '1.0.0',
            'author'      => 'Your Name',
            'description' => '插件描述',
        ];
    }

    public function install(): void
    {
        // 执行数据库迁移
    }

    public function uninstall(): void
    {
        // 回滚数据库迁移
    }

    public function enable(): void
    {
        // 启用插件时执行
    }

    public function disable(): void
    {
        // 禁用插件时执行
    }
}
```

## 插件安装

```bash
php bin/hyperf.php plugin:install my-plugin
```

## 插件卸载

```bash
php bin/hyperf.php plugin:uninstall my-plugin
```
