# Decomkt Ads · Laravel 重构版

这是原 Decomkt Ads 后台的独立 Laravel 重构项目。原 Next.js 仓库仅作为只读对照，未被修改。

## 技术栈

- Laravel 13 / PHP 8.4
- Vue 3 + TypeScript + Inertia 3
- Tailwind CSS 4 + Vite 8
- MySQL 8.4
- Redis 7.4（Session、Cache、Queue）
- Nginx、Laravel Queue Worker、Laravel Scheduler
- Docker Compose 一键本地环境

## 一键启动

已安装 Docker Desktop 时，在项目目录执行：

```powershell
docker compose up -d --build
```

首次启动会自动完成：复制环境文件、安装 Composer 依赖、生成应用密钥、创建 MySQL 数据库表、写入初始数据、安装前端依赖并启动 Vite。

打开：<http://localhost:8000>

默认管理员：

- 账号：`admin`
- 邮箱：`admin@decomkt.local`
- 密码：`admin123456`

本地端口：Web `8000`、Vite `5173`、MySQL `3307`、Redis `6380`。

## 已实现范围

- Macfox 风格登录、顶栏、分组侧栏、仪表盘和全部原后台业务页面
- 人员账号、状态、职位、重置密码、角色与店铺授权
- RBAC 页面权限、角色权限分配、非员工访问拦截
- 多店铺 CRUD、成员授权、店铺级数据与凭证隔离
- 任务看板、状态流转、负责人、优先级、通知与团队统计
- 学生折扣开关、活动配置基础数据、公开状态接口
- Google / Trustpilot / 官网评论、Reddit、风险与资源记录
- 数据驱动的风险扫描、舆情周报生成与归档
- 系统与店铺的多平台凭证管理；密钥使用 Laravel Crypt 加密且不回显
- 原 `/api/*` 路径兼容层，未配置第三方凭证时返回明确状态

第三方平台（Shopify、Meta、Google、TikTok、Bing、Criteo、飞书等）必须使用真实平台凭证才可能获取真实数据。凭证可在“系统设置 / 店铺设置”中加密配置。

## 验证命令

```powershell
docker compose exec app php artisan test
docker compose exec vite npm run types:check
docker compose exec vite npm run build
docker compose exec app php artisan route:list
```

逐页对照与浏览器 QA 记录位于：

- `docs/ORIGINAL_PAGE_AUDIT.md`
- `docs/LARAVEL_PAGE_QA.md`
- `docs/MIGRATION_SCOPE.md`

## 数据与安全

- `.env`、`vendor`、`node_modules`、构建产物均不提交。
- 对外注册已关闭；新账号只能由系统管理员创建。
- 默认管理员不能被停用或删除。
- 系统密钥与店铺密钥按允许清单写入，并使用应用密钥加密。
- Docker 数据保存在 `mysql_data` 与 `redis_data` 卷中。
