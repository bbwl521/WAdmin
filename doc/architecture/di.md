# 依赖注入

Hyperf 提供了强大的依赖注入容器，支持自动解析和注入。

## 基本用法

```php
use Hyperf\Di\Annotation\Inject;

class UserService
{
    #[Inject]
    protected UserRepository $repository;
    
    #[Inject]
    protected EventDispatcherInterface $eventDispatcher;
}
```

## 注解注入方式

| 注解 | 说明 |
|------|------|
| `#[Inject]` | 按类型自动注入 |
| `#[Inject(identifier: 'named')]` | 按标识注入 |
| `#[Value('app.name')]` | 注入配置值 |

## 配置文件注入

```php
class ConfigService
{
    #[Value('app.name')]
    protected string $appName;
    
    #[Value('databases.default.pool.min')]
    protected int $minConnections;
}
```

## 接口与实现绑定

在 `config/autoload/dependencies.php` 中配置：

```php
return [
    \App\Contract\UserServiceInterface::class => \App\Service\UserService::class,
];
```
