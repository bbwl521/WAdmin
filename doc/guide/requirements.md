# 环境要求

## 运行环境

| 依赖 | 最低版本 | 推荐版本 |
|------|---------|---------|
| PHP | 8.1 | 8.2+ |
| Swoole | 5.0 | 5.1+ |
| MySQL | 8.0 | 8.0+ |
| Redis | 6.0 | 7.0+ |
| Composer | 2.0 | 2.5+ |

## PHP 扩展

```bash
# 必要扩展
swoole >= 5.0
redis
pdo_mysql
mbstring
json
curl
fileinfo
bcmath

# 可选扩展
sockets
pcntl
event
```

## 系统要求

- Linux / macOS / WSL2
- 不推荐在 Windows 原生环境运行
