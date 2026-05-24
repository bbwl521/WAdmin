# Repository 层开发

Repository 层封装数据访问逻辑，提供一致的数据操作接口。

## 基本示例

```php
<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\User;
use Hyperf\Database\Model\Builder;

class UserRepository
{
    public function search(array $params = []): array
    {
        return User::query()
            ->when($params['name'] ?? null, fn (Builder $q, $v) =>
                $q->where('name', 'like', "%{$v}%")
            )
            ->when($params['status'] ?? null, fn (Builder $q, $v) =>
                $q->where('status', $v)
            )
            ->paginate();
    }

    public function findById(int $id): ?User
    {
        return User::find($id);
    }

    public function create(array $data): User
    {
        return User::create($data);
    }

    public function update(int $id, array $data): bool
    {
        return User::where('id', $id)->update($data) > 0;
    }

    public function delete(int $id): bool
    {
        return User::destroy($id) > 0;
    }
}
```

## 最佳实践

- 使用 `when()` 方法构建条件查询
- 复杂查询可以封装为 Eloquent Scope
- 返回类型明确，便于 IDE 提示
