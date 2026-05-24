# 开发规范

## 分层架构

严格遵守 Controller → Service → Repository → Model 的分层架构。

### Controller 层

- 负责接收请求、参数校验、调用 Service
- **禁止**在 Controller 中编写业务逻辑
- **禁止**直接操作 Model

### Service 层

- 业务逻辑的核心层
- 一个 Service 方法只做一件事
- 通过依赖注入获取 Repository

### Repository 层

- 封装数据查询逻辑
- 使用 Hyperf 的 Model 查询构建器
- 复杂查询封装为 Scope

### Model 层

- 定义数据表映射关系
- 定义关联关系
- 定义访问器与修改器

## 依赖注入

```php
use Hyperf\Di\Annotation\Inject;

class UserService
{
    #[Inject]
    protected UserRepository $repository;
}
```

## 异常处理

使用自定义异常类，统一错误响应格式。
