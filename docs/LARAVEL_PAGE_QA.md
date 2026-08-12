# Laravel 重构版浏览器验收记录

验收日期：2026-08-12。环境：Codex 内置 Chromium，`http://localhost:8000`，Docker Compose 本地服务。

## 逐页结果

以下 36 个登录后页面均已在真实浏览器中逐一打开；Vue/Inertia 全部成功挂载，无白屏、无致命异常，逐页检查时控制台错误数均为 0。

| 分区 | 已验收路径 |
| --- | --- |
| 工作台 | `/`、`/workspace/brand`、`/ecommerce/amazon`、`/ecommerce/shopify`、`/ecommerce/student-discounts`、`/ads/campaign`、`/workspace/ai-brain` |
| 付费广告 | `/ads/target`、`/ads/facebook`、`/ads/google`、`/ads/tiktok`、`/ads/bing`、`/ads/criteo` |
| 自然流量 / 中台 | `/organic/seo`、`/organic/social`、`/organic/kol`、`/organic/edm`、`/organic/affiliate`、`/ecommerce/design` |
| 舆情 | `/reputation/overview`、`/reputation/google-reviews`、`/reputation/trustpilot`、`/reputation/website-reviews`、`/reputation/reddit`、`/reputation/risk-sync`、`/reputation/weekly-report` |
| 协作 / 管理 | `/collab/kanban`、`/collab/team`、`/employees`、`/permissions`、`/roles`、`/plugins`、`/store-settings`、`/settings`、`/stores`、`/account/profile` |

登录页也已检查：Macfox Logo、渐变背景、深色登录卡、用户名/邮箱兼容登录、密码输入与错误状态均正常。

## 浏览器交互验收

- 使用浏览器创建“浏览器验收任务”，指派给系统管理员，验证卡片出现后依次执行“开始”和“完成”，最后删除测试任务。
- 打开人员编辑弹窗，确认姓名、职位、状态、角色、店铺授权和重置密码入口均可用。
- 打开店铺成员授权弹窗，确认默认管理员所有者保护和成员多选保存入口。
- 在店铺设置中切换到 Google Ads，确认 5 项凭证均可独立加密保存；在系统设置中切换邮件服务，确认 4 项邮件配置可编辑。
- 启用学生折扣并保存，确认 Inertia 刷新后仍显示启用；验收后恢复为停用状态。
- 生成本周舆情周报并保存，确认历史归档出现 `2026 年第 33 周 / 系统管理员 → 管理层 / 已保存`。

## 自动化验证

- PHP：39 通过、2 跳过（公开注册按设计关闭），后续新增业务用例单独验证 3 通过 / 23 断言。
- Vue TypeScript：`vue-tsc --noEmit` 通过。
- 业务数据库：MySQL 共 56 张表；Redis 用于 Session、Cache 与 Queue。
- Docker：Nginx、App、Vite、MySQL、Redis、Queue、Scheduler 均正常运行。
