# 控制器开发

## 基本示例

```php
<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\UserService;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\GetMapping;
use Hyperf\HttpServer\Annotation\PostMapping;
use Psr\Http\Message\ResponseInterface;

#[Controller(prefix: 'user')]
class UserController extends AbstractController
{
    #[Inject]
    protected UserService $service;

    #[GetMapping('index')]
    public function index(): ResponseInterface
    {
        $users = $this->service->getList();
        return $this->success($users);
    }

    #[PostMapping('store')]
    public function store(): ResponseInterface
    {
        $data = $this->request->all();
        $this->service->create($data);
        return $this->success();
    }
}
```

## 注解说明

| 注解 | 说明 |
|------|------|
| `#[Controller]` | 控制器类注解，设置路由前缀 |
| `#[GetMapping]` | GET 请求路由 |
| `#[PostMapping]` | POST 请求路由 |
| `#[PutMapping]` | PUT 请求路由 |
| `#[DeleteMapping]` | DELETE 请求路由 |
| `#[Inject]` | 依赖注入注解 |
