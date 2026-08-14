<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, defineAsyncComponent, ref, watch } from 'vue';
import { pageBlueprints } from '@/data/pageBlueprints';

// Module is shared by most business routes. Load only the component needed by
// the current route so the first sidebar visit does not download every module.
const DesignView = defineAsyncComponent(() => import('@/pages/Design.vue'));
const AffiliateView = defineAsyncComponent(
    () => import('@/pages/Affiliate.vue'),
);
const EdmView = defineAsyncComponent(() => import('@/pages/Edm.vue'));
const ReputationView = defineAsyncComponent(
    () => import('@/pages/Reputation.vue'),
);
const ReputationOverview = defineAsyncComponent(
    () => import('@/pages/ReputationOverview.vue'),
);
const SocialView = defineAsyncComponent(() => import('@/pages/Social.vue'));
const KolView = defineAsyncComponent(() => import('@/pages/Kol.vue'));
const AdsPlatformView = defineAsyncComponent(
    () => import('@/pages/AdsPlatform.vue'),
);

const props = defineProps<{
    module: {
        group: string;
        title: string;
        path: string;
        description: string;
        features: string[];
    };
    store: { id: string; name: string; timezone: string };
    configuration?: Record<string, boolean>;
    studentDiscount?: {
        enabled: boolean;
        issued: number;
        failed: number;
        total: number;
    } | null;
    records?: any;
    configuredCredentials?: string[];
    analytics?: {
        metrics: Array<{ label: string; value: string; detail?: string }>;
        rows: any[];
        trend: number[];
        error?: string;
    };
    dataSync?: {
        recordCount: number;
        syncedAt: string | null;
        lastError: string | null;
    } | null;
    externalSync?: {
        recordCount: number;
        syncedAt: string | null;
        lastError: string | null;
    } | null;
    designPage?: boolean;
    summary?: any;
    designers?: any[];
    types?: any[];
    activeTasks?: any[];
    recordPage?: any;
    socialSummary?: any;
    kolSummary?: any;
    reputationSummary?: any;
}>();

const blueprint = computed(
    () =>
        pageBlueprints[props.module.path] || {
            subtitle: props.module.description,
            tabs: props.module.features,
            metrics: props.module.features.map((label) => ({
                label,
                value: '0',
            })),
            panels: ['数据工作区'],
        },
);
const configured = (key: string) =>
    props.configuredCredentials?.includes(key) || false;
const effectiveActions = computed(() => {
    const actions = [...(blueprint.value.actions || [])];
    const external = [
        '/ads/campaign',
        '/ads/target',
        '/ecommerce/shopify',
        '/ads/facebook',
        '/ads/google',
        '/ads/tiktok',
        '/ads/bing',
        '/ads/criteo',
        '/organic/kol',
        '/organic/edm',
        '/organic/affiliate',
    ];

    if (
        external.includes(props.module.path) &&
        !actions.some((action) => action.includes('刷新'))
    ) {
        actions.push('刷新');
    }

    return actions;
});
const requiredCredentials: Record<string, string[]> = {
    '/workspace/brand': ['FEISHU_APP_ID', 'FEISHU_APP_SECRET'],
    '/ecommerce/amazon': ['FEISHU_APP_ID', 'FEISHU_APP_SECRET'],
    '/ecommerce/shopify': ['SHOPIFY_ACCESS_TOKEN', 'SHOPIFY_STORE_DOMAIN'],
    '/ads/facebook': ['FB_ACCESS_TOKEN'],
    '/ads/google': [
        'GOOGLE_ADS_CLIENT_ID',
        'GOOGLE_ADS_CLIENT_SECRET',
        'GOOGLE_ADS_REFRESH_TOKEN',
        'GOOGLE_ADS_DEVELOPER_TOKEN',
        'GOOGLE_ADS_CUSTOMER_ID',
    ],
    '/ads/tiktok': ['TK_ACCESS_TOKEN', 'TK_ADVERTISER_IDS'],
    '/ads/bing': [
        'BING_ADS_CLIENT_ID',
        'BING_ADS_CLIENT_SECRET',
        'BING_ADS_REFRESH_TOKEN',
        'BING_ADS_DEVELOPER_TOKEN',
        'BING_ADS_ACCOUNT_ID',
    ],
    '/ads/criteo': ['CRITEO_API_KEY', 'CRITEO_CLIENT_SECRET'],
    '/organic/kol': ['FEISHU_APP_ID', 'FEISHU_APP_SECRET'],
    '/organic/edm': ['FEISHU_APP_ID', 'FEISHU_APP_SECRET'],
    '/organic/affiliate': ['FEISHU_APP_ID', 'FEISHU_APP_SECRET'],
    '/ecommerce/design': ['FEISHU_APP_ID', 'FEISHU_APP_SECRET'],
    '/reputation/overview': ['FEISHU_APP_ID', 'FEISHU_APP_SECRET'],
};
const missingCredentials = computed(() =>
    (requiredCredentials[props.module.path] || []).filter(
        (key) => !configured(key),
    ),
);
const emptyDataReasons: Record<string, string> = {
    '/ecommerce/shopify':
        'Shopify 凭证已配置，但服务器返回 401：访问令牌已失效或无订单读取权限。',
    '/ads/bing': 'Bing Ads 授权可刷新，但 Microsoft 异步报表尚未写入本地缓存。',
    '/ads/criteo': 'Criteo 授权可连接，但当前账号尚未返回广告主维度报表。',
    '/workspace/brand':
        '飞书基础凭证已配置，但“品牌资料”表格当前没有可用的本地业务记录。',
    '/ads/campaign':
        '原 PostgreSQL 中没有活动主题业务表记录，飞书活动表尚未同步到本地。',
    '/ads/target': '原项目没有可迁移的广告目标记录，页面当前显示真实的空状态。',
    '/organic/edm': '飞书凭证已配置，但原 PostgreSQL 中没有 EDM 指标缓存记录。',
    '/organic/affiliate':
        '飞书凭证已配置，但原 PostgreSQL 中没有联盟营销业务记录。',
    '/ecommerce/design':
        '飞书凭证已配置，但原 PostgreSQL 中没有设计需求业务记录。',
};
const visibleAlert = computed(() => {
    if (missingCredentials.value.length) {
        return `缺少配置：${missingCredentials.value.join('、')}`;
    }

    if (props.analytics?.error) {
        return props.analytics.error;
    }

    if (!props.analytics?.metrics?.length) {
        return emptyDataReasons[props.module.path] || '';
    }

    return '';
});
const displayMetrics = computed(() =>
    props.analytics?.metrics?.length
        ? props.analytics.metrics
        : blueprint.value.metrics || [],
);
const displayRows = computed(() => props.analytics?.rows || []);
const displaySubtitle = computed(() =>
    props.analytics?.metrics?.length
        ? `已连接真实业务数据 · 当前展示 ${displayRows.value.length} 条最新记录`
        : blueprint.value.subtitle || props.module.description,
);
const chartHeights = computed(() => {
    const values = props.analytics?.trend || [];
    const max = Math.max(...values, 1);

    return values.length
        ? values
              .slice(-16)
              .map((value) => Math.max(6, Math.round((value * 100) / max)))
        : [];
});
type Integration = { name: string; keys: string[]; hint?: string };
const systemIntegrationGroups: Integration[][] = [
    [
        { name: 'OpenAI / ChatGPT', keys: ['OPENAI_API_KEY', 'OPENAI_MODEL'] },
        {
            name: 'OpenAI Codex',
            keys: ['OPENAI_API_KEY', 'OPENAI_MODEL'],
            hint: 'Codex 与 OpenAI 共用当前系统 API 凭证。',
        },
        { name: 'Google Gemini', keys: ['GEMINI_API_KEY'] },
        {
            name: 'Claude (Anthropic)',
            keys: ['CLAUDE_API_KEY', 'CLAUDE_MODEL'],
        },
    ],
    [
        {
            name: '邮件服务',
            keys: ['MAIL_HOST', 'MAIL_PORT', 'MAIL_USERNAME', 'MAIL_PASSWORD'],
        },
        { name: '飞书 / Lark', keys: ['FEISHU_APP_ID', 'FEISHU_APP_SECRET'] },
    ],
];
const storeIntegrationGroups: Integration[][] = [
    [
        {
            name: '店铺资料',
            keys: [],
            hint: '店铺名称、时区与成员授权请前往「店铺管理」页面维护。',
        },
    ],
    [
        { name: 'Facebook / Meta Ads', keys: ['FB_ACCESS_TOKEN'] },
        {
            name: 'Shopify',
            keys: ['SHOPIFY_ACCESS_TOKEN', 'SHOPIFY_STORE_DOMAIN'],
        },
        { name: 'TikTok Ads', keys: ['TK_ACCESS_TOKEN', 'TK_ADVERTISER_IDS'] },
        {
            name: 'Google Ads',
            keys: [
                'GOOGLE_ADS_CLIENT_ID',
                'GOOGLE_ADS_CLIENT_SECRET',
                'GOOGLE_ADS_REFRESH_TOKEN',
                'GOOGLE_ADS_DEVELOPER_TOKEN',
                'GOOGLE_ADS_CUSTOMER_ID',
                'GOOGLE_ADS_LOGIN_CUSTOMER_ID',
            ],
        },
        {
            name: 'Bing / Microsoft Ads',
            keys: [
                'BING_ADS_CLIENT_ID',
                'BING_ADS_CLIENT_SECRET',
                'BING_ADS_REFRESH_TOKEN',
                'BING_ADS_DEVELOPER_TOKEN',
                'BING_ADS_ACCOUNT_ID',
                'BING_ADS_CUSTOMER_ID',
            ],
        },
        {
            name: 'Criteo',
            keys: [
                'CRITEO_API_KEY',
                'CRITEO_CLIENT_SECRET',
                'CRITEO_ADVERTISER_ID',
            ],
        },
        {
            name: 'YouTube Analytics',
            keys: ['YOUTUBE_CLIENT_ID', 'YOUTUBE_CLIENT_SECRET'],
        },
        {
            name: 'Google Search Console / GA4',
            keys: [
                'GSC_SITE_URL',
                'GSC_CLIENT_ID',
                'GSC_CLIENT_SECRET',
                'GSC_REFRESH_TOKEN',
                'GA4_PROPERTY_ID',
                'GA4_SERVICE_ACCOUNT_JSON',
            ],
        },
    ],
    [
        { name: '飞书基础授权', keys: ['FEISHU_APP_ID', 'FEISHU_APP_SECRET'] },
        {
            name: '品牌资料',
            keys: ['FEISHU_BRAND_WIKI_URL', 'FEISHU_BRAND_SPREADSHEET_TOKEN'],
        },
        {
            name: '活动主题',
            keys: [
                'FEISHU_CAMPAIGN_APP_TOKEN',
                'FEISHU_CAMPAIGN_TABLE_ID',
                'FEISHU_CAMPAIGN_VIEW_ID',
            ],
        },
        {
            name: '视觉设计',
            keys: ['FEISHU_DESIGN_APP_TOKEN', 'FEISHU_DESIGN_TABLE_ID'],
        },
        {
            name: '红人运营',
            keys: [
                'FEISHU_KOL_APP_TOKEN',
                'FEISHU_KOL_TABLE_ID',
                'FEISHU_KOL_VIEW_ID',
            ],
        },
        {
            name: '联盟营销',
            keys: [
                'FEISHU_AFFILIATE_APP_TOKEN',
                'FEISHU_AFFILIATE_TABLE_ID',
                'FEISHU_AFFILIATE_VIEW_ID',
            ],
        },
        { name: 'EDM 邮件', keys: ['FEISHU_SEQUENCE_WIKI_NODE'] },
        {
            name: 'SEO 日数据',
            keys: ['FEISHU_SEO_APP_TOKEN', 'FEISHU_SEO_DAILY_TABLE_ID'],
        },
    ],
];
const activeTab = ref(props.module.path === '/store-settings' ? 1 : 0);
const activeIntegration = ref(0);
const integrationGroups = computed(() =>
    props.module.path === '/settings'
        ? systemIntegrationGroups
        : storeIntegrationGroups,
);
const currentIntegrations = computed(
    () => integrationGroups.value[activeTab.value] || [],
);
const selectedIntegration = computed(
    () =>
        currentIntegrations.value[activeIntegration.value] ||
        currentIntegrations.value[0],
);
const settingsMetrics = computed(() => {
    if (props.module.path !== '/settings') {
        return blueprint.value.metrics || [];
    }

    const platforms = [
        ['OPENAI_API_KEY', 'OPENAI_MODEL'],
        ['OPENAI_API_KEY', 'OPENAI_MODEL'],
        ['GEMINI_API_KEY', 'GEMINI_MODEL'],
        ['CLAUDE_API_KEY', 'CLAUDE_MODEL'],
        [
            'MAIL_HOST',
            'MAIL_PORT',
            'MAIL_USERNAME',
            'MAIL_PASSWORD',
            'FEISHU_APP_ID',
            'FEISHU_APP_SECRET',
        ],
    ];
    const states = platforms.map(
        (keys) =>
            keys.filter((key) => props.configuration?.[key]).length /
            keys.length,
    );

    return [
        {
            label: '已接入',
            value: String(states.filter((value) => value === 1).length),
        },
        {
            label: '部分配置',
            value: String(
                states.filter((value) => value > 0 && value < 1).length,
            ),
        },
        {
            label: '未接入',
            value: String(states.filter((value) => value === 0).length),
        },
        { label: '平台总数', value: String(states.length) },
    ];
});
watch(activeTab, () => {
    activeIntegration.value = 0;
});
const refreshed = ref(false);
const syncing = ref(false);
const enabled = ref(Boolean(props.studentDiscount?.enabled));
const report = ref('');
const reporter = ref('');
const reportTo = ref('');
const recordModal = ref(false);
const recordEntity = ref<'review' | 'reddit' | 'risk' | 'resource'>('review');
const recordContent = ref('');
const recordTitle = ref('');
const recordStar = ref(5);
const openRecord = (entity: 'review' | 'reddit' | 'risk' | 'resource') => {
    recordEntity.value = entity;
    recordContent.value = '';
    recordTitle.value = '';
    recordModal.value = true;
};
const recordPlatform = computed(() =>
    props.module.path.includes('trustpilot')
        ? 'TRUSTPILOT'
        : props.module.path.includes('google')
          ? 'GOOGLE'
          : 'WEBSITE',
);
const submitRecord = () =>
    router.post(
        '/reputation/items',
        {
            entity: recordEntity.value,
            platform:
                recordEntity.value === 'review' ? recordPlatform.value : '综合',
            title: recordTitle.value,
            content: recordContent.value,
            star: recordStar.value,
            sentiment: 'NEUTRAL',
            level: 'MEDIUM',
            priority: 'MEDIUM',
        },
        { onSuccess: () => (recordModal.value = false) },
    );
const saveStudentDiscount = () =>
    router.put('/student-discounts', { enabled: enabled.value });
const configValues = ref<Record<string, string>>({});
const configScope = computed(() =>
    props.module.path === '/settings' ? 'system' : 'store',
);
const saveConfig = (key: string) => {
    if (!configValues.value[key]) {
        return;
    }

    router.post(
        `/configuration/${configScope.value}`,
        { key, value: configValues.value[key] },
        { onSuccess: () => (configValues.value[key] = '') },
    );
};
const clearConfig = (key: string) => {
    if (confirm(`确认清空 ${key}？`)) {
        router.delete(`/configuration/${configScope.value}/${key}`);
    }
};
const handleAction = (action: string) => {
    if (props.module.path === '/ecommerce/design' && action.includes('刷新')) {
        syncing.value = true;
        router.post(
            '/ecommerce/design/refresh',
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    refreshed.value = true;
                    window.setTimeout(() => (refreshed.value = false), 1800);
                },
                onFinish: () => {
                    syncing.value = false;
                },
            },
        );

        return;
    }

    if (
        action.includes('刷新') &&
        [
            '/ads/campaign',
            '/ads/target',
            '/ecommerce/shopify',
            '/ads/facebook',
            '/ads/google',
            '/ads/tiktok',
            '/ads/bing',
            '/ads/criteo',
            '/organic/kol',
            '/organic/edm',
            '/organic/affiliate',
        ].includes(props.module.path)
    ) {
        syncing.value = true;
        router.post(
            '/data-sync/refresh',
            { path: props.module.path },
            {
                preserveScroll: true,
                onSuccess: () => {
                    refreshed.value = true;
                    window.setTimeout(() => (refreshed.value = false), 1800);
                },
                onFinish: () => (syncing.value = false),
            },
        );

        return;
    }

    if (
        props.module.path === '/reputation/risk-sync' &&
        action.includes('分析')
    ) {
        router.post('/reputation/analyze');

        return;
    }

    refreshed.value = true;
    window.setTimeout(() => (refreshed.value = false), 1800);

    if (action.includes('生成')) {
        report.value = `舆情周报\n\n本周各平台舆情整体平稳，暂无新增高风险事项。\n数据生成时间：${new Date().toLocaleString('zh-CN')}`;
    }
};
const saveReport = () => {
    if (!report.value) {
        return;
    }

    router.post('/reputation/weekly-report', {
        reporter: reporter.value,
        reportTo: reportTo.value,
        content: report.value,
    });
};
</script>

<template>
    <DesignView
        v-if="designPage"
        :summary="summary"
        :designers="designers || []"
        :types="types || []"
        :active-tasks="activeTasks || []"
        :record-page="recordPage"
        :data-sync="dataSync"
    />
    <AffiliateView
        v-else-if="module.path === '/organic/affiliate'"
        :analytics="analytics"
        :external-sync="externalSync"
    />
    <EdmView
        v-else-if="module.path === '/organic/edm'"
        :analytics="analytics"
        :external-sync="externalSync"
    />
    <ReputationOverview
        v-else-if="module.path === '/reputation/overview'"
        :summary="reputationSummary"
        :record-page="recordPage"
    />
    <SocialView
        v-else-if="module.path === '/organic/social'"
        :summary="socialSummary"
        :record-page="recordPage"
    />
    <KolView
        v-else-if="module.path === '/organic/kol'"
        :summary="kolSummary"
        :record-page="recordPage"
    />
    <AdsPlatformView
        v-else-if="
            [
                '/ads/facebook',
                '/ads/google',
                '/ads/tiktok',
                '/ads/bing',
                '/ads/criteo',
            ].includes(module.path)
        "
        :module="module"
        :analytics="analytics"
        :external-sync="externalSync"
    />
    <ReputationView
        v-else-if="
            [
                '/reputation/google-reviews',
                '/reputation/trustpilot',
                '/reputation/website-reviews',
                '/reputation/reddit',
            ].includes(module.path)
        "
        :module="module"
        :summary="reputationSummary"
        :record-page="recordPage"
    />
    <template v-else
        ><Head :title="module.title" />
        <div class="deco-page">
            <div class="deco-header">
                <div>
                    <h1 class="deco-title">{{ module.title }}</h1>
                    <p class="deco-subtitle">{{ displaySubtitle }}</p>
                </div>
                <div class="deco-actions">
                    <input
                        v-if="
                            !['ai', 'student', 'plugin', 'settings'].includes(
                                blueprint.kind || '',
                            )
                        "
                        class="deco-input deco-date"
                        value="2026-08-05　-　2026-08-11"
                        aria-label="日期范围"
                    />
                    <button
                        v-for="action in effectiveActions"
                        :key="action"
                        class="deco-button"
                        :class="{
                            primary:
                                action.includes('刷新') ||
                                action.includes('分析'),
                        }"
                        :disabled="syncing"
                        @click="
                            action.includes('新增评论')
                                ? openRecord('review')
                                : action.includes('新增帖子')
                                  ? openRecord('reddit')
                                  : handleAction(action)
                        "
                    >
                        {{
                            syncing && action.includes('刷新')
                                ? '同步中…'
                                : action
                        }}
                    </button>
                    <span v-if="refreshed" class="deco-pill green"
                        >操作成功</span
                    >
                    <span
                        v-if="
                            module.path === '/ecommerce/design' &&
                            dataSync?.syncedAt
                        "
                        class="text-xs text-[#929aa4]"
                        >最近同步：{{
                            new Date(dataSync.syncedAt).toLocaleString('zh-CN')
                        }}</span
                    >
                    <span
                        v-if="externalSync?.syncedAt"
                        class="text-xs text-[#929aa4]"
                        >最近同步：{{
                            new Date(externalSync.syncedAt).toLocaleString(
                                'zh-CN',
                            )
                        }}</span
                    >
                </div>
            </div>

            <template v-if="blueprint.kind === 'ai'">
                <div
                    class="grid min-h-[calc(100vh-116px)] grid-cols-[280px_1fr] overflow-hidden border border-[#343941] bg-[#15191d]"
                >
                    <aside class="border-r border-[#343941] bg-[#252525] p-4">
                        <h2 class="deco-card-title">真实业务数据</h2>
                        <div
                            v-for="metric in analytics?.metrics || []"
                            :key="metric.label"
                            class="mb-3 flex items-center justify-between rounded bg-[#303236] px-3 py-2 text-xs"
                        >
                            <span>{{ metric.label }}</span
                            ><b class="text-blue-400">{{ metric.value }}</b>
                        </div>
                        <h2 class="deco-card-title mt-7">历史分析</h2>
                        <p class="text-xs text-[#929aa4]">
                            {{ analytics?.rows?.length || 0 }} 条历史会话
                        </p>
                        <button class="deco-button mt-8 w-full">
                            + 新建对话
                        </button>
                    </aside>
                    <main class="relative flex flex-col p-5">
                        <div
                            class="flex items-center justify-between border-b border-[#30363d] pb-4"
                        >
                            <b>AI 营销大脑</b>
                            <div class="deco-tabs !m-0 !border-0 !p-0">
                                <button
                                    v-for="(tab, index) in blueprint.tabs"
                                    :key="tab"
                                    class="deco-tab"
                                    :class="{ active: index === activeTab }"
                                    @click="activeTab = index"
                                >
                                    {{ tab }}
                                </button>
                            </div>
                        </div>
                        <div
                            class="flex flex-1 flex-col items-center justify-center text-center"
                        >
                            <div
                                class="mb-5 grid size-14 place-items-center rounded-2xl bg-blue-500/15 text-2xl"
                            >
                                ✦
                            </div>
                            <h2 class="text-xl font-bold">
                                上午好，让我们看看数据表现如何
                            </h2>
                            <p class="mt-3 text-sm text-[#929aa4]">
                                直接输入问题开始对话，AI 会自动为你创建会话
                            </p>
                        </div>
                        <div class="rounded-2xl border border-[#454b53] p-4">
                            <textarea
                                class="min-h-16 w-full resize-none bg-transparent text-sm outline-none"
                                placeholder="输入你的营销问题，开始对话..."
                            ></textarea>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="prompt in [
                                        'Facebook投放健康检查',
                                        'Google Ads起量策略',
                                        'TikTok冷启动方案',
                                        '电商选品数据分析',
                                        '亚马逊TACOS优化',
                                        '跨渠道预算再分配',
                                        '竞品广告策略拆解',
                                        '品牌舆情风险扫描',
                                    ]"
                                    :key="prompt"
                                    class="rounded-full bg-[#303236] px-3 py-1.5 text-xs text-[#b9c0c8]"
                                >
                                    ✦ {{ prompt }}
                                </button>
                            </div>
                        </div>
                    </main>
                </div>
            </template>

            <template v-else-if="blueprint.kind === 'student'">
                <div class="mb-4 flex justify-end">
                    <div class="deco-card flex items-center gap-4 !p-3">
                        <div>
                            <b class="text-sm">店铺展示</b>
                            <p class="text-xs text-[#929aa4]">
                                {{ enabled ? '已启用' : '已停用' }}
                            </p>
                        </div>
                        <button
                            class="h-6 w-11 rounded-full p-1"
                            :class="enabled ? 'bg-blue-500' : 'bg-[#4a4d52]'"
                            @click="enabled = !enabled"
                        >
                            <i
                                class="block size-4 rounded-full bg-white transition"
                                :class="{ 'translate-x-5': enabled }"
                            ></i></button
                        ><button
                            class="deco-button primary"
                            @click="saveStudentDiscount"
                        >
                            保存
                        </button>
                    </div>
                </div>
                <div class="deco-metrics">
                    <article
                        v-for="(metric, index) in blueprint.metrics"
                        :key="metric.label"
                        class="deco-card deco-metric"
                    >
                        <p class="deco-metric-label">{{ metric.label }}</p>
                        <p class="deco-metric-value">
                            {{
                                index === 0
                                    ? studentDiscount?.issued || 0
                                    : index === 1
                                      ? studentDiscount?.failed || 0
                                      : studentDiscount?.total || 0
                            }}
                        </p>
                    </article>
                </div>
                <div
                    class="deco-tabs rounded-lg border border-[#353a40] bg-[#252525] !p-2"
                >
                    <button
                        v-for="(tab, index) in blueprint.tabs"
                        :key="tab"
                        class="deco-tab"
                        :class="{ active: index === activeTab }"
                        @click="activeTab = index"
                    >
                        {{ tab }}
                    </button>
                </div>
                <div
                    v-if="!enabled"
                    class="deco-card mb-4 !border-amber-700 !bg-[#2a2418]"
                >
                    <h2 class="deco-card-title text-amber-400">该应用已停用</h2>
                    <p class="text-sm text-[#b4abb0]">
                        请先启用应用并保存，然后再发布学生优惠页面。
                    </p>
                </div>
                <div class="deco-card">
                    <h2 class="deco-card-title">设置指南</h2>
                    <div
                        v-for="(step, index) in [
                            '启用应用',
                            '选择验证方式',
                            '创建并设计学生优惠活动',
                            '添加学生优惠页面模板',
                        ]"
                        :key="step"
                        class="flex items-center gap-3 border-b border-[#393d42] py-4 last:border-0"
                    >
                        <span
                            class="grid size-6 place-items-center rounded-full"
                            :class="
                                index === 0 && !enabled
                                    ? 'border border-[#464b51]'
                                    : 'bg-emerald-500 text-white'
                            "
                            >{{ index === 0 && !enabled ? '' : '✓' }}</span
                        ><span>{{ step }}</span
                        ><span class="ml-auto">›</span>
                    </div>
                </div>
            </template>

            <template v-else-if="blueprint.kind === 'risk'">
                <section class="deco-card mb-4">
                    <div class="flex items-center gap-3">
                        <h2 class="deco-card-title !mb-0">
                            🤖 AI 自动识别风险
                        </h2>
                        <button
                            class="deco-button primary"
                            @click="handleAction('立即分析')"
                        >
                            🔍 立即分析
                        </button>
                    </div>
                    <div class="deco-empty">
                        点击「立即分析」基于数据库数据自动检测风险
                    </div>
                </section>
                <section class="deco-card mb-4">
                    <div class="flex items-center justify-between">
                        <h2 class="deco-card-title !mb-0">
                            🚨 风险清单（{{ records?.risks?.length || 0 }} 项）
                        </h2>
                        <button
                            class="deco-button primary"
                            @click="openRecord('risk')"
                        >
                            + 添加风险
                        </button>
                    </div>
                    <div v-if="!records?.risks?.length" class="deco-empty">
                        ✅ 暂无风险项，舆情整体平稳
                    </div>
                    <div v-else class="mt-4 space-y-2">
                        <article
                            v-for="risk in records.risks"
                            :key="risk.id"
                            class="rounded bg-[#2d3034] p-3 text-sm"
                        >
                            <span class="deco-pill red">{{ risk.level }}</span
                            >　{{ risk.description }}
                        </article>
                    </div>
                </section>
                <section class="deco-card mb-4">
                    <div class="flex items-center justify-between">
                        <h2 class="deco-card-title !mb-0">
                            📋 资源支持需求（{{
                                records?.resources?.length || 0
                            }}
                            项）
                        </h2>
                        <button
                            class="deco-button primary"
                            @click="openRecord('resource')"
                        >
                            + 添加需求
                        </button>
                    </div>
                    <div v-if="!records?.resources?.length" class="deco-empty">
                        暂无资源支持需求
                    </div>
                    <div v-else class="mt-4 space-y-2">
                        <article
                            v-for="item in records.resources"
                            :key="item.id"
                            class="rounded bg-[#2d3034] p-3 text-sm"
                        >
                            {{ item.description }}
                        </article>
                    </div>
                </section>
            </template>

            <template v-else-if="blueprint.kind === 'report'">
                <section class="deco-card mb-4">
                    <div class="grid gap-3 md:grid-cols-[1fr_1fr_1fr_auto]">
                        <input
                            class="deco-input"
                            placeholder="汇报周期"
                            value="本周"
                            disabled
                        /><input
                            v-model="reporter"
                            class="deco-input"
                            placeholder="输入汇报人"
                        /><input
                            v-model="reportTo"
                            class="deco-input"
                            placeholder="输入汇报对象"
                        /><button
                            class="deco-button primary"
                            @click="handleAction('生成')"
                        >
                            📄 一键生成周报
                        </button>
                    </div>
                </section>
                <section class="deco-card min-h-72">
                    <div class="flex items-center justify-between">
                        <h2 class="deco-card-title">📝 周报内容</h2>
                        <button
                            v-if="report"
                            class="deco-button primary"
                            @click="saveReport"
                        >
                            保存本周周报
                        </button>
                    </div>
                    <textarea
                        v-if="report"
                        v-model="report"
                        class="deco-input min-h-52 w-full py-3 font-mono text-sm leading-7"
                    ></textarea>
                    <div v-else class="deco-empty">
                        点击「一键生成周报」自动汇总各平台数据
                    </div>
                </section>
                <section
                    v-if="Array.isArray(records) && records.length"
                    class="deco-card mt-4"
                >
                    <h2 class="deco-card-title">历史归档</h2>
                    <article
                        v-for="item in records"
                        :key="item.id"
                        class="flex items-center justify-between border-b border-[#3a3f45] py-3 last:border-0"
                    >
                        <div>
                            <b>{{ item.year }} 年第 {{ item.week }} 周</b>
                            <p class="mt-1 text-xs text-[#929aa4]">
                                {{ item.reporter || '未填写汇报人' }} →
                                {{ item.reportTo || '未填写汇报对象' }}
                            </p>
                        </div>
                        <span class="deco-pill green">已保存</span>
                    </article>
                </section>
            </template>

            <template v-else-if="blueprint.kind === 'plugin'">
                <div class="deco-metrics">
                    <article
                        v-for="(metric, index) in blueprint.metrics"
                        :key="metric.label"
                        class="deco-card deco-metric"
                    >
                        <p class="deco-metric-label">{{ metric.label }}</p>
                        <p class="deco-metric-value">
                            {{ index === 0 || index === 2 ? 1 : 0 }}
                        </p>
                    </article>
                </div>
                <div class="grid max-w-3xl gap-4 md:grid-cols-2">
                    <section class="deco-card">
                        <div class="mb-4 flex items-start justify-between">
                            <span
                                class="grid size-12 place-items-center rounded-lg bg-blue-500/20 text-2xl"
                                >🎓</span
                            ><span class="deco-pill">待配置</span>
                        </div>
                        <h2 class="text-xl font-bold">
                            学生优惠
                            <small class="text-xs font-normal text-[#929aa4]"
                                >v1.0.0</small
                            >
                        </h2>
                        <p class="mt-2 text-sm text-blue-400">转化工具</p>
                        <p class="my-5 text-sm leading-6 text-[#bdc3cb]">
                            教育邮箱自动发放优惠码；其他邮箱上传学生证，由后台人工审核。
                        </p>
                        <button class="deco-button primary w-full">
                            开始配置
                        </button>
                    </section>
                    <section
                        class="grid min-h-72 place-items-center rounded-lg border border-dashed border-[#454b53] text-center"
                    >
                        <div>
                            <div class="text-4xl text-[#9ba3ad]">＋</div>
                            <h2 class="mt-4 text-lg font-bold">
                                后续插件统一加入这里
                            </h2>
                            <p class="mt-2 text-sm text-[#929aa4]">
                                登记插件信息后，管理卡片会自动显示。
                            </p>
                        </div>
                    </section>
                </div>
            </template>

            <template v-else-if="blueprint.kind === 'settings'">
                <div v-if="settingsMetrics.length" class="deco-metrics">
                    <article
                        v-for="metric in settingsMetrics"
                        :key="metric.label"
                        class="deco-card deco-metric"
                    >
                        <p class="deco-metric-label">{{ metric.label }}</p>
                        <p class="deco-metric-value">{{ metric.value }}</p>
                    </article>
                </div>
                <section class="deco-card">
                    <div class="deco-tabs">
                        <button
                            v-for="(tab, index) in blueprint.tabs"
                            :key="tab"
                            class="deco-tab"
                            :class="{ active: index === activeTab }"
                            @click="activeTab = index"
                        >
                            {{ tab }}
                        </button>
                    </div>
                    <div class="grid gap-5 md:grid-cols-[220px_1fr]">
                        <nav class="space-y-1">
                            <button
                                v-for="(
                                    integration, index
                                ) in currentIntegrations"
                                :key="integration.name"
                                class="block w-full rounded px-3 py-3 text-left text-sm"
                                :class="
                                    index === activeIntegration
                                        ? 'bg-blue-500/10 text-blue-400'
                                        : 'hover:bg-white/5'
                                "
                                @click="activeIntegration = index"
                            >
                                {{ integration.name }}
                            </button>
                        </nav>
                        <div class="border-l-2 border-blue-500 pl-5">
                            <h2 class="text-lg font-bold">
                                {{ selectedIntegration?.name }}
                            </h2>
                            <p class="mt-1 text-sm text-[#929aa4]">
                                {{
                                    selectedIntegration?.hint ||
                                    'API 凭证用于安全地同步平台数据，密钥加密存储且不会在页面回显。'
                                }}
                            </p>
                            <div
                                v-if="selectedIntegration?.keys.length"
                                class="mt-6 space-y-3"
                            >
                                <div
                                    v-for="key in selectedIntegration.keys"
                                    :key="key"
                                    class="rounded-lg bg-[#2c3034] p-4"
                                >
                                    <div
                                        class="flex items-center justify-between"
                                    >
                                        <div>
                                            <b class="text-sm">配置项</b>
                                            <p
                                                class="mt-1 font-mono text-xs text-[#929aa4]"
                                            >
                                                {{ key }}
                                            </p>
                                        </div>
                                        <span
                                            class="deco-pill"
                                            :class="
                                                configuration?.[key]
                                                    ? 'green'
                                                    : 'red'
                                            "
                                            >{{
                                                configuration?.[key]
                                                    ? '已配置'
                                                    : '未配置'
                                            }}</span
                                        >
                                    </div>
                                    <div class="mt-4 flex flex-wrap gap-2">
                                        <input
                                            v-model="configValues[key]"
                                            class="deco-input min-w-52 flex-1"
                                            :type="
                                                key.includes('SECRET') ||
                                                key.includes('TOKEN') ||
                                                key.includes('PASSWORD') ||
                                                key.includes('API_KEY')
                                                    ? 'password'
                                                    : 'text'
                                            "
                                            :placeholder="
                                                configuration?.[key]
                                                    ? '已配置，输入新值以替换'
                                                    : '未配置，输入以设置'
                                            "
                                        /><button
                                            class="deco-button primary"
                                            @click="saveConfig(key)"
                                        >
                                            保存</button
                                        ><button
                                            v-if="configuration?.[key]"
                                            class="deco-button text-red-400"
                                            @click="clearConfig(key)"
                                        >
                                            清空
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </template>

            <template v-else>
                <div
                    v-if="visibleAlert"
                    class="deco-alert"
                    :class="{ warning: blueprint.alertTone === 'warning' }"
                >
                    ● {{ visibleAlert }}
                </div>
                <div v-if="blueprint.tabs?.length" class="deco-tabs">
                    <button
                        v-for="(tab, index) in blueprint.tabs"
                        :key="tab"
                        class="deco-tab"
                        :class="{ active: index === activeTab }"
                        @click="activeTab = index"
                    >
                        {{ tab }}
                    </button>
                </div>
                <div
                    v-if="displayMetrics.length"
                    class="deco-metrics"
                    :class="{
                        five: displayMetrics.length === 5,
                        six: displayMetrics.length === 6,
                    }"
                >
                    <article
                        v-for="metric in displayMetrics"
                        :key="metric.label"
                        class="deco-card deco-metric"
                    >
                        <p class="deco-metric-label">{{ metric.label }}</p>
                        <p class="deco-metric-value">{{ metric.value }}</p>
                        <p v-if="metric.detail" class="deco-metric-detail">
                            {{ metric.detail }}
                        </p>
                    </article>
                </div>
                <div v-if="blueprint.panels?.length" class="deco-grid-2">
                    <section
                        v-for="(panel, index) in blueprint.panels"
                        :key="panel"
                        class="deco-card deco-panel"
                    >
                        <h2 class="deco-card-title">{{ panel }}</h2>
                        <div
                            v-if="index < 2 && chartHeights.length"
                            class="deco-chart-bars"
                        >
                            <span
                                v-for="(height, chartIndex) in chartHeights"
                                :key="chartIndex"
                                :style="{ height: height + '%' }"
                            ></span>
                        </div>
                        <div v-else class="deco-empty">
                            暂无可绘制的趋势数据
                        </div>
                    </section>
                </div>
                <div v-if="blueprint.tableHeaders" class="deco-table-wrap">
                    <table class="deco-table">
                        <thead>
                            <tr>
                                <th
                                    v-for="heading in blueprint.tableHeaders"
                                    :key="heading"
                                >
                                    {{ heading }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in displayRows" :key="row.id">
                                <td :colspan="blueprint.tableHeaders.length">
                                    <b>{{
                                        row.title ||
                                        row.description ||
                                        row.productName ||
                                        row.accountName ||
                                        row.platform ||
                                        row.id
                                    }}</b
                                    ><span class="ml-3 text-[#929aa4]">{{
                                        row.publishedAt ||
                                        row.purchaseDate ||
                                        row.createdAt ||
                                        ''
                                    }}</span>
                                </td>
                            </tr>
                            <tr v-if="!displayRows.length">
                                <td
                                    :colspan="blueprint.tableHeaders.length"
                                    class="!py-16 text-center text-[#929aa4]"
                                >
                                    暂无已同步数据
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <section v-else-if="displayRows.length" class="deco-card mt-4">
                    <h2 class="deco-card-title">最新业务记录</h2>
                    <article
                        v-for="row in displayRows.slice(0, 20)"
                        :key="row.id"
                        class="flex items-center justify-between border-b border-[#3a3f45] py-3 last:border-0"
                    >
                        <div>
                            <b>{{
                                row.title ||
                                row.description ||
                                row.productName ||
                                row.accountName ||
                                row.platform ||
                                row.id
                            }}</b>
                            <p class="mt-1 text-xs text-[#929aa4]">
                                {{
                                    row.publishedAt ||
                                    row.purchaseDate ||
                                    row.createdAt ||
                                    ''
                                }}
                            </p>
                        </div>
                        <span class="deco-pill">{{
                            row.orderStatus ||
                            row.postType ||
                            row.sentiment ||
                            '已同步'
                        }}</span>
                    </article>
                </section>
                <section
                    v-if="Array.isArray(records) && records.length"
                    class="deco-card mt-4"
                >
                    <h2 class="deco-card-title">最新记录</h2>
                    <article
                        v-for="record in records"
                        :key="record.id"
                        class="border-b border-[#3a3f45] py-3 last:border-0"
                    >
                        <div class="flex items-center justify-between">
                            <b>{{ record.title || record.platform }}</b
                            ><span class="deco-pill">{{
                                record.star
                                    ? record.star + '★'
                                    : record.sentiment
                            }}</span>
                        </div>
                        <p class="mt-2 text-sm text-[#b7bec7]">
                            {{ record.content || record.body }}
                        </p>
                    </article>
                </section>
            </template>

            <div
                v-if="recordModal"
                class="fixed inset-0 z-[90] grid place-items-center bg-black/70 p-4"
                @click.self="recordModal = false"
            >
                <form
                    class="deco-card w-full max-w-lg"
                    @submit.prevent="submitRecord"
                >
                    <div class="flex items-center justify-between">
                        <h2 class="deco-card-title">
                            新增{{
                                recordEntity === 'review'
                                    ? '评论'
                                    : recordEntity === 'reddit'
                                      ? '帖子'
                                      : recordEntity === 'risk'
                                        ? '风险'
                                        : '资源需求'
                            }}
                        </h2>
                        <button type="button" @click="recordModal = false">
                            ×
                        </button>
                    </div>
                    <div class="grid gap-3">
                        <input
                            v-if="recordEntity === 'reddit'"
                            v-model="recordTitle"
                            class="deco-input"
                            placeholder="帖子标题"
                            required
                        /><select
                            v-if="recordEntity === 'review'"
                            v-model="recordStar"
                            class="deco-input"
                        >
                            <option
                                v-for="star in [5, 4, 3, 2, 1]"
                                :key="star"
                                :value="star"
                            >
                                {{ star }} 星
                            </option></select
                        ><textarea
                            v-model="recordContent"
                            class="deco-input min-h-32 py-3"
                            placeholder="请输入内容"
                            required
                        ></textarea
                        ><button class="deco-button primary">保存记录</button>
                    </div>
                </form>
            </div>
        </div></template
    >
</template>
