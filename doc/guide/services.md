# Service 层开发

Service 层是业务逻辑的核心，所有业务代码应在此层完成。

## 基本示例

```php
<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\UserRepository;
use Hyperf\Di\Annotation\Inject;
use Psr\EventDispatcher\EventDispatcherInterface;

class UserService
{
    #[Inject]
    protected UserRepository $repository;

    #[Inject]
    protected EventDispatcherInterface $eventDispatcher;

    public function getList(array $params = []): array
    {
        return $this->repository->search($params);
    }

    public function findById(int $id): ?object
    {
        return $this->repository->findById($id);
    }

    public function create(array $data): object
    {
        $user = $this->repository->create($data);
        // 触发事件
        $this->eventDispatcher->dispatch(new UserCreated($user));
        return $user;
    }

    public function update(int $id, array $data): bool
    {
        return $this->repository->update($id, $data);
    }

    public function delete(int $id): bool
    {
        return $this->repository->delete($id);
    }
}
```

## 设计原则

- 单一职责：每个 Service 方法只负责一个业务操作
- 事务管理：涉及多表操作时使用数据库事务
- 事件驱动：关键业务节点触发事件，解耦扩展逻辑
