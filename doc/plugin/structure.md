# 插件结构详解

## composer.json

插件必须包含 `composer.json`，声明依赖和自动加载：

```json
{
    "name": "plugin/my-plugin",
    "type": "mineadmin-plugin",
    "require": {
        "php": ">=8.1"
    },
    "autoload": {
        "psr-4": {
            "Plugin\\MyPlugin\\": "src/"
        }
    }
}
```

## ConfigProvider.php

配置提供者用于向系统注册服务：

```php
<?php

namespace Plugin\MyPlugin;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => [
                // 接口绑定
            ],
            'commands' => [
                // 注册命令
            ],
            'listeners' => [
                // 注册事件监听器
            ],
            'annotations' => [
                'scan' => [
                    'paths' => [
                        __DIR__,
                    ],
                ],
            ],
        ];
    }
}
```

## 数据库迁移

插件的数据库迁移文件放在 `databases/migrations/` 目录下，安装插件时系统会自动执行迁移。
