# 安装部署

## 获取项目

```bash
git clone <repository-url>
cd WAdmin
```

## 安装依赖

```bash
composer install
```

## 环境配置

```bash
# 复制环境配置文件
cp .env.example .env

# 编辑 .env 文件配置数据库、Redis 等
vim .env
```

## 数据库迁移

```bash
php bin/hyperf.php migrate
```

## 启动服务

```bash
# 开发环境
php bin/hyperf.php start

# 生产环境（守护进程）
php bin/hyperf.php start -d

# 热更新开发
php watch
```

## 访问系统

服务默认启动在 `http://localhost:9501`
