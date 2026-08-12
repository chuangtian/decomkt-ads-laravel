# 原后台逐页对照清单

本清单来自 2026-08-12 在 `localhost:3000` 对原 Next.js 后台的真实浏览器检查。原项目以只读目录挂载在独立容器中；Laravel 重构项目运行于 `localhost:8000`。

| 路径 | 页面核心结构 |
| --- | --- |
| `/` | 日期筛选、Shopify GMV/订单/客单价/日均 GMV、Amazon 数据卡 |
| `/workspace/brand` | 飞书品牌资料、刷新、密码隐藏提示、错误/空状态 |
| `/ecommerce/amazon` | 日期筛选、导入、刷新、7 个页签、8 个指标、双趋势图 |
| `/ecommerce/shopify` | 日期与对比周期、刷新、配置告警、经营指标 |
| `/ecommerce/student-discounts` | 启用开关、保存、3 个指标、8 个设置页签、设置指南 |
| `/ads/campaign` | 总览/复盘/策划/日历、5 个指标、时间线、4 个分析面板 |
| `/workspace/ai-brain` | 渠道评分、历史会话、模型切换、聊天区、8 个快捷分析 |
| `/ads/target` | 月份筛选、人员页签、目标汇总 |
| `/ads/facebook` | 日期/对比/刷新、配置告警、总览/趋势/系列/素材/文案/AI |
| `/ads/google` | 日期/刷新、配置告警、总览/趋势/系列/搜索词/关键词/周报/目标/AI |
| `/ads/tiktok` | 日期/刷新、配置告警、总览/趋势/系列/素材/AI |
| `/ads/bing` | 日期/对比/刷新/重新授权、配置告警、总览/趋势/系列/AI |
| `/ads/criteo` | 日期/刷新、配置告警、总览/趋势/系列 |
| `/organic/seo` | 目标/综合/GSC/GA、4 个节奏指标、实时刷新、目标分类 |
| `/organic/social` | 日期/对比、4 个页签、5 个指标、漏斗、平台对比、帖子表格 |
| `/organic/kol` | 合作/资源/推荐/洞察、4 个指标、趋势/Top10/散点、合作表格 |
| `/organic/edm` | 5 个页签、6 个指标、飞书配置状态 |
| `/organic/affiliate` | 数据看板、加载失败卡、重试 |
| `/ecommerce/design` | 效率总览/需求列表、飞书配置状态 |
| `/reputation/overview` | 日期、目标/评论/Reddit/Threads/AI、数据状态 |
| `/reputation/google-reviews` | 3 个页签、4 个指标、星级/话题、待回复评价 |
| `/reputation/trustpilot` | 新增评论、3 个页签、6 个指标、星级/情感/话题/洞察 |
| `/reputation/website-reviews` | 新增评论、3 个页签、5 个指标、SKU/话题/洞察 |
| `/reputation/reddit` | 新增帖子、3 个页签、6 个指标、情绪/话题/版块 |
| `/reputation/risk-sync` | 日期、AI 分析、风险清单、资源支持需求 |
| `/reputation/weekly-report` | 周期/汇报人/汇报对象、一键生成、复制、保存 |
| `/collab/kanban` | 日期、新建任务、4 个指标、四列看板 |
| `/collab/team` | 4 个指标、成员卡片、职位分布 |
| `/employees` | 搜索/状态/角色筛选、新增、人员表格与操作 |
| `/permissions` | 页面选择、添加权限、权限表格与删除 |
| `/roles` | 添加角色、角色卡、权限数、人数、编辑 |
| `/plugins` | 3 个指标、学生优惠插件卡、配置入口 |
| `/store-settings` | Logo/凭证/飞书链接；9 类业务凭证侧栏与密钥编辑 |
| `/settings` | 4 个接入指标、AI/系统分类、四供应商、模型列表、密钥管理 |
| `/stores` | 新建、店铺状态、成员授权、编辑、删除 |
| `/profile` | 账户资料、账号/邮箱/状态/角色、修改密码 |

除以上 36 个登录后页面外，登录页也已检查：Macfox Logo、渐变背景、420px 深色卡片、用户名/邮箱与密码输入、圆角主按钮。
