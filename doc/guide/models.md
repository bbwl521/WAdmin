# 数据模型

## 基本示例

```php
<?php

declare(strict_types=1);

namespace App\Model;

use Hyperf\Database\Model\SoftDeletes;

class User extends Model
{
    use SoftDeletes;

    protected ?string $table = 'user';

    protected array $fillable = [
        'name',
        'email',
        'password',
        'status',
    ];

    protected array $casts = [
        'status' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_role');
    }
}
```

## 模型约定

| 属性 | 说明 |
|------|------|
| `$table` | 数据表名 |
| `$fillable` | 允许批量赋值的字段 |
| `$guarded` | 禁止批量赋值的字段 |
| `$casts` | 属性类型转换 |
| `$hidden` | JSON 序列化时隐藏的字段 |
