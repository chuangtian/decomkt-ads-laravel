export type PageMetric = { label: string; value: string; detail?: string };
export type PageBlueprint = {
    subtitle?: string;
    tabs?: string[];
    metrics?: PageMetric[];
    alert?: string;
    alertTone?: 'danger' | 'warning';
    actions?: string[];
    panels?: string[];
    tableHeaders?: string[];
    kind?:
        | 'analytics'
        | 'simple'
        | 'student'
        | 'ai'
        | 'risk'
        | 'report'
        | 'plugin'
        | 'settings';
};

const zero = (labels: string[]): PageMetric[] =>
    labels.map((label) => ({
        label,
        value:
            label.includes('率') || label.includes('占比')
                ? '0%'
                : label.includes('评分')
                  ? '0.0★'
                  : '0',
    }));

export const pageBlueprints: Record<string, PageBlueprint> = {
    '/workspace/brand': {
        kind: 'simple',
        subtitle: '账号、密码、执照等团队共享资料 — 来源：飞书「品牌资料」表格',
        actions: ['刷新'],
        alert: '加载失败：飞书凭证未配置，请在系统设置中配置 FEISHU_APP_ID 和 FEISHU_APP_SECRET',
        alertTone: 'danger',
        panels: ['品牌资料'],
    },
    '/ecommerce/amazon': {
        subtitle: '数据来源：飞书数据 · 0 天 · 暂无数据',
        actions: ['导入文件', '刷新'],
        alert: '飞书凭证未配置：请在系统设置中配置 FEISHU_APP_ID 和 FEISHU_APP_SECRET',
        alertTone: 'warning',
        tabs: [
            '📊 总览',
            '📢 广告',
            '📋 广告明细',
            '📅 每日明细',
            '🔗 监控链接',
            '🎯 AI 分析',
            '📊 目标',
        ],
        metrics: [
            { label: '净销售额', value: '$0.00', detail: '环比：暂无' },
            { label: '退货', value: '$0.00', detail: '环比：暂无' },
            { label: '广告花费', value: '$0.00', detail: '环比：暂无' },
            { label: 'ROI', value: '0.00×', detail: '环比：暂无' },
            { label: '数据天数', value: '0 天' },
            { label: '日均净销售', value: '$0.00' },
            { label: '广告占比', value: '0.00%' },
            { label: '广告销售占比', value: '0.00%' },
        ],
        panels: ['销售额 & ROI 趋势', '广告花费 & 广告销售额趋势'],
    },
    '/ecommerce/shopify': {
        subtitle: 'Shopify Admin API 实时数据',
        actions: ['刷新'],
        alert: 'SHOPIFY_ACCESS_TOKEN 未配置',
        tabs: ['📊 总览', '📈 趋势分析', '📦 商品表现', '🛒 订单明细'],
        metrics: [
            { label: 'GMV', value: '$0' },
            { label: '订单数', value: '0' },
            { label: '客单价', value: '$0' },
            { label: '日均 GMV', value: '$0' },
        ],
        panels: ['销售额与订单趋势', '商品表现'],
    },
    '/ecommerce/student-discounts': {
        kind: 'student',
        subtitle: '教育邮箱自动发放；学生证申请由后台人工审核。',
        tabs: [
            '仪表板',
            '管理折扣',
            '验证选项',
            '显示设置',
            '常规设置',
            '电子邮件设置',
            '数据分析',
            '帮助',
        ],
        metrics: zero(['已发放', '失败', '总请求']),
    },
    '/ads/campaign': {
        subtitle: 'Campaign Themes · 活动主题管理与复盘分析',
        tabs: ['📊 总览', '📝 复盘分析', '📋 活动策划', '📅 活动日历'],
        metrics: [
            { label: '总 GMV', value: '$0' },
            { label: '广告花费', value: '$0' },
            { label: '订单', value: '0' },
            { label: '整体 ROI', value: '—' },
            { label: '整体 CVR', value: '—' },
        ],
        panels: [
            '活动节奏时间线',
            'ROI 与日均销售额趋势',
            'CVR 转化率与日均店铺访问',
            '规模 × 效率（预算分配视图）',
        ],
    },
    '/workspace/ai-brain': {
        kind: 'ai',
        subtitle: '跨渠道数据分析与营销策略助手',
        tabs: ['Gemini', 'GPT', 'Claude'],
    },
    '/ads/target': {
        subtitle: '各付费广告渠道的月度目标进度汇总',
        actions: ['筛选'],
        tabs: ['总目标', '黄智诚', '王静彬', '潘舒晴'],
        metrics: zero(['目标销售额', '目标花费', '目标 ROI', '完成率']),
        panels: ['渠道目标完成进度'],
    },
    '/ads/facebook': {
        subtitle: 'Meta Marketing API 实时数据',
        actions: ['刷新'],
        alert: 'FB_ACCESS_TOKEN 未配置',
        tabs: [
            '📊 总览',
            '📈 趋势分析',
            '📋 广告系列',
            '🎨 优质素材',
            '✍️ 优质文案',
            '💡 AI 分析',
        ],
        metrics: [
            { label: '广告花费', value: '$0' },
            { label: '购买价值', value: '$0' },
            { label: 'ROAS', value: '0.00×' },
            { label: '转化', value: '0' },
        ],
        panels: ['花费与转化趋势', '广告系列表现'],
    },
    '/ads/google': {
        subtitle: 'Google Ads API 实时数据',
        actions: ['刷新'],
        alert: 'Google Ads 环境变量未完整配置（需要 CLIENT_ID、CLIENT_SECRET、REFRESH_TOKEN、DEVELOPER_TOKEN、CUSTOMER_ID）',
        tabs: [
            '📊 总览',
            '📈 趋势分析',
            '📋 广告系列',
            '🔍 搜索词',
            '🔑 关键词',
            '📅 周报',
            '🎯 目标',
            '💡 AI 分析',
        ],
        metrics: [
            { label: '广告花费', value: '$0' },
            { label: '转化价值', value: '$0' },
            { label: 'ROAS', value: '0.00×' },
            { label: '点击', value: '0' },
        ],
        panels: ['花费与转化趋势', '广告系列表现'],
    },
    '/ads/tiktok': {
        subtitle: 'TikTok Business API 实时数据',
        actions: ['刷新'],
        alert: 'TK_ACCESS_TOKEN 或 TK_ADVERTISER_IDS 未配置',
        tabs: [
            '📊 总览',
            '📈 趋势分析',
            '📋 广告系列',
            '🎬 优质素材',
            '💡 AI 分析',
        ],
        metrics: [
            { label: '广告花费', value: '$0' },
            { label: '转化价值', value: '$0' },
            { label: 'ROAS', value: '0.00×' },
            { label: '展示', value: '0' },
        ],
        panels: ['投放趋势', '素材表现'],
    },
    '/ads/bing': {
        subtitle: 'Microsoft Ads API 实时数据',
        actions: ['重新授权', '刷新'],
        alert: 'Bing Ads 环境变量未完整配置（需要 CLIENT_ID、CLIENT_SECRET、REFRESH_TOKEN、DEVELOPER_TOKEN、ACCOUNT_ID）',
        tabs: ['📊 总览', '📈 趋势分析', '📋 广告系列', '🎯 AI 分析'],
        metrics: [
            { label: '广告花费', value: '$0' },
            { label: '转化价值', value: '$0' },
            { label: 'ROAS', value: '0.00×' },
            { label: '点击', value: '0' },
        ],
        panels: ['账户趋势', '广告系列'],
    },
    '/ads/criteo': {
        subtitle: 'Criteo Marketing Solutions API 实时数据',
        actions: ['刷新'],
        alert: 'Criteo 环境变量未配置（需要 CRITEO_API_KEY 和 CRITEO_CLIENT_SECRET）',
        tabs: ['📊 总览', '📈 趋势分析', '📋 广告系列'],
        metrics: [
            { label: '广告花费', value: '$0' },
            { label: '销售额', value: '$0' },
            { label: 'ROAS', value: '0.00×' },
            { label: '转化', value: '0' },
        ],
        panels: ['再营销趋势', '广告系列'],
    },
    '/organic/seo': {
        subtitle: '月度目标进度 · 综合分析 · GSC / GA 源数据',
        actions: ['刷新最新数据'],
        tabs: ['🎯 目标看板', '📈 综合看板', '🔍 GSC 源数据', '📊 GA 源数据'],
        metrics: [
            {
                label: '总体判断',
                value: '节奏正常',
                detail: '大部分目标按当前节奏可控',
            },
            { label: '预计达标', value: '0/0', detail: '月底预估可达标数' },
            { label: '需要追赶', value: '0', detail: '低于进度线且预估不达标' },
            { label: '数据范围', value: '—', detail: '最新可用数据日期' },
        ],
        panels: ['实时刷新数据', '核心目标进度'],
    },
    '/organic/social': {
        subtitle: '以 Instagram 官媒为主，快速复盘每日发布与互动表现。',
        tabs: ['平台拆解', '📝 每日复盘', '📋 周报', '🎯 AI 分析'],
        metrics: zero(['帖子数', '浏览量', '点赞', '评论', '分享']),
        panels: ['品牌官媒漏斗', '平台漏斗对比'],
        tableHeaders: [
            '平台',
            '类型',
            '内容',
            '发布日期',
            '浏览量',
            '赞',
            '评论',
            '分享',
            'ER',
            '操作',
        ],
    },
    '/organic/kol': {
        subtitle: '数据来源：飞书多维表格 · 0 条合作记录',
        alert: '飞书数据加载失败：飞书环境变量未配置（需要 FEISHU_APP_ID 和 FEISHU_APP_SECRET）',
        alertTone: 'warning',
        tabs: ['合作数据明细', '资源库', 'AI 推荐', '红人运营增长洞察'],
        metrics: [
            { label: '合作红人数', value: '0', detail: '持平' },
            { label: '总浏览量', value: '0' },
            { label: '均播', value: '0' },
            { label: '平均互动率', value: '0.00%' },
        ],
        panels: [
            '发布日期浏览趋势',
            '红人浏览 TOP 10',
            '数据变化趋势',
            '浏览量 / 互动率分析',
        ],
        tableHeaders: [
            '红人 title',
            '合作价格',
            '发布日期',
            '均播',
            '合作链接',
            '赞',
            '评',
            '浏览',
            '互动率',
            '操作',
        ],
    },
    '/organic/edm': {
        subtitle: '邮件列表健康度与自动化序列分析',
        alert: '飞书环境变量未配置（需要 FEISHU_APP_ID 和 FEISHU_APP_SECRET）',
        tabs: [
            '📊 总览',
            '📧 序列表现',
            '👥 用户分层',
            '🎯 目标看板',
            '💡 AI 分析',
        ],
        metrics: [
            { label: 'Email Revenue', value: '$0' },
            { label: 'Open Rate', value: '—' },
            { label: 'CTR', value: '—' },
            { label: 'CVR', value: '—' },
            { label: 'Subscribers', value: '—' },
            { label: 'Unsubscribe Rate', value: '—' },
        ],
    },
    '/organic/affiliate': {
        kind: 'simple',
        subtitle: 'Affiliate Marketing · 联盟合作伙伴数据看板',
        alert: '数据加载失败：飞书凭证未配置，请在系统设置中配置 FEISHU_APP_ID 和 FEISHU_APP_SECRET',
        actions: ['重试'],
        panels: ['联盟合作伙伴数据'],
    },
    '/ecommerce/design': {
        kind: 'simple',
        subtitle: '设计需求管理与效率追踪',
        actions: ['刷新'],
        alert: '飞书环境变量未配置',
        tabs: ['📊 效率总览', '📋 需求列表'],
        panels: ['设计效率总览'],
    },
    '/reputation/overview': {
        subtitle:
            '跨平台舆情监控与数据汇总 — Trustpilot · 官网评论 · Google 直评 · Reddit',
        alert: '目标数据加载失败：飞书凭证未配置',
        tabs: [
            '🎯 目标看板',
            '💬 评论管理',
            '🔴 Reddit',
            '🧵 Threads',
            '💡 AI 分析',
        ],
        metrics: zero(['总评论', '正面占比', '待回复', '风险项']),
        panels: ['跨平台声量', '情绪趋势'],
    },
    '/reputation/google-reviews': {
        subtitle: 'Google 商家评价管理与回复策略',
        tabs: ['📊 总览', '💬 评价管理', '🎯 AI 分析'],
        metrics: [
            { label: 'Google 评分', value: '4.4★', detail: '↑ 0.2' },
            { label: '总评价数', value: '2,841', detail: '本月 +184' },
            { label: '本月新增', value: '184', detail: '↑ 12%' },
            { label: '未回复', value: '4', detail: '需跟进' },
        ],
        panels: ['星级分布', '高频话题', '🚨 待回复评价（4 条）'],
    },
    '/reputation/trustpilot': {
        subtitle: '评价管理与口碑分析',
        actions: ['+ 新增评论'],
        tabs: ['📊 总览', '💬 评价管理', '🎯 AI 分析'],
        metrics: [
            { label: '综合评分', value: '0.0★', detail: '基于 0 条' },
            { label: '总评价数', value: '0' },
            { label: '正面占比', value: '0%' },
            { label: '负面占比', value: '0%' },
            { label: '本周新增', value: '0' },
            { label: '紧急处理', value: '0' },
        ],
        panels: [
            '星级分布',
            '情感分布',
            'Top Mentions',
            '差评集中问题',
            '好评可用率',
        ],
    },
    '/reputation/website-reviews': {
        subtitle: 'macfoxbike.com 用户评价',
        actions: ['+ 新增评论'],
        tabs: ['📊 总览', '💬 评价列表', '🎯 AI 分析'],
        metrics: zero([
            '官网评论数',
            '综合评分',
            '正面占比',
            '负面占比',
            '紧急待处理',
        ]),
        panels: [
            '星级分布',
            'SKU 评分对比',
            'Top Mentions',
            '差评集中问题',
            '好评可用率',
        ],
    },
    '/reputation/reddit': {
        subtitle: '品牌舆情监控与社区运营',
        actions: ['+ 新增帖子'],
        tabs: ['📊 总览', '💬 帖子追踪', '🎯 运营清单'],
        metrics: zero([
            '帖子总数',
            '总 Upvotes',
            '总评论数',
            '正面情绪',
            '负面情绪',
            '监控版块',
        ]),
        panels: ['整体情绪分布', '🔥 高频话题', '版块监控（0 个版块）'],
    },
    '/reputation/risk-sync': {
        kind: 'risk',
        subtitle: 'AI 自动识别 + 手动添加舆情风险项',
        actions: ['🔍 立即分析'],
    },
    '/reputation/weekly-report': {
        kind: 'report',
        subtitle: '基于各平台数据自动生成舆情周报',
    },
    '/store-settings': {
        kind: 'settings',
        subtitle:
            '配置当前店铺的业务凭证与飞书数据链接。所有密钥按店铺加密隔离。',
        tabs: ['店铺资料', '业务凭证', '飞书数据链接'],
    },
    '/settings': {
        kind: 'settings',
        subtitle: '管理系统基础配置和平台 API 凭证接入状态',
        metrics: [
            { label: '已接入', value: '1' },
            { label: '部分配置', value: '0' },
            { label: '未接入', value: '4' },
            { label: '平台总数', value: '5' },
        ],
        tabs: ['🤖 AI 大模型', '⚙️ 系统基础'],
    },
};
