<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { pageBlueprints } from '@/data/pageBlueprints';

const props = defineProps<{
    module: { group: string; title: string; path: string; description: string; features: string[] };
    store: { id: string; name: string; timezone: string };
    configuration?: Record<string, boolean>;
    studentDiscount?: { enabled: boolean; issued: number; failed: number; total: number } | null;
    records?: any;
}>();

const blueprint = computed(() => pageBlueprints[props.module.path] || {
    subtitle: props.module.description,
    tabs: props.module.features,
    metrics: props.module.features.map((label) => ({ label, value: '0' })),
    panels: ['数据工作区'],
});
type Integration = { name: string; keys: string[]; hint?: string };
const systemIntegrationGroups: Integration[][] = [
    [
        { name: 'OpenAI / ChatGPT', keys: ['OPENAI_API_KEY', 'OPENAI_MODEL'] },
        { name: 'OpenAI Codex', keys: ['CODEX_API_KEY'] },
        { name: 'Google Gemini', keys: ['GEMINI_API_KEY'] },
        { name: 'Claude (Anthropic)', keys: ['ANTHROPIC_API_KEY'] },
    ],
    [
        { name: '邮件服务', keys: ['MAIL_HOST', 'MAIL_PORT', 'MAIL_USERNAME', 'MAIL_PASSWORD'] },
        { name: '飞书 / Lark', keys: ['FEISHU_APP_ID', 'FEISHU_APP_SECRET'] },
    ],
];
const storeIntegrationGroups: Integration[][] = [
    [{ name: '店铺资料', keys: [], hint: '店铺名称、时区与成员授权请前往「店铺管理」页面维护。' }],
    [
        { name: 'Facebook / Meta Ads', keys: ['FB_ACCESS_TOKEN'] },
        { name: 'Shopify', keys: ['SHOPIFY_ACCESS_TOKEN', 'SHOPIFY_STORE_DOMAIN'] },
        { name: 'TikTok Ads', keys: ['TK_ACCESS_TOKEN', 'TK_ADVERTISER_IDS'] },
        { name: 'Google Ads', keys: ['GOOGLE_ADS_CLIENT_ID', 'GOOGLE_ADS_CLIENT_SECRET', 'GOOGLE_ADS_REFRESH_TOKEN', 'GOOGLE_ADS_DEVELOPER_TOKEN', 'GOOGLE_ADS_CUSTOMER_ID'] },
        { name: 'Bing / Microsoft Ads', keys: ['BING_ADS_CLIENT_ID', 'BING_ADS_CLIENT_SECRET', 'BING_ADS_REFRESH_TOKEN', 'BING_ADS_DEVELOPER_TOKEN', 'BING_ADS_ACCOUNT_ID'] },
        { name: 'Criteo', keys: ['CRITEO_API_KEY', 'CRITEO_CLIENT_SECRET'] },
        { name: 'YouTube Analytics', keys: ['YOUTUBE_CLIENT_ID', 'YOUTUBE_CLIENT_SECRET'] },
        { name: 'Google Search Console / GA4', keys: ['GSC_SITE_URL', 'GA4_PROPERTY_ID'] },
    ],
    [{ name: '飞书 / Lark', keys: ['FEISHU_APP_ID', 'FEISHU_APP_SECRET'] }],
];
const activeTab = ref(props.module.path === '/store-settings' ? 1 : 0);
const activeIntegration = ref(0);
const integrationGroups = computed(() => props.module.path === '/settings' ? systemIntegrationGroups : storeIntegrationGroups);
const currentIntegrations = computed(() => integrationGroups.value[activeTab.value] || []);
const selectedIntegration = computed(() => currentIntegrations.value[activeIntegration.value] || currentIntegrations.value[0]);
watch(activeTab, () => {
 activeIntegration.value = 0;
});
const refreshed = ref(false);
const enabled = ref(Boolean(props.studentDiscount?.enabled));
const report = ref('');
const reporter = ref('');
const reportTo = ref('');
const recordModal = ref(false);
const recordEntity = ref<'review'|'reddit'|'risk'|'resource'>('review');
const recordContent = ref('');
const recordTitle = ref('');
const recordStar = ref(5);
const openRecord = (entity: 'review'|'reddit'|'risk'|'resource') => {
 recordEntity.value=entity; recordContent.value=''; recordTitle.value=''; recordModal.value=true;
};
const recordPlatform = computed(() => props.module.path.includes('trustpilot')?'TRUSTPILOT':props.module.path.includes('google')?'GOOGLE':'WEBSITE');
const submitRecord = () => router.post('/reputation/items', {entity:recordEntity.value,platform:recordEntity.value==='review'?recordPlatform.value:'综合',title:recordTitle.value,content:recordContent.value,star:recordStar.value,sentiment:'NEUTRAL',level:'MEDIUM',priority:'MEDIUM'}, {onSuccess:()=>recordModal.value=false});
const saveStudentDiscount = () => router.put('/student-discounts', {enabled:enabled.value});
const configValues = ref<Record<string, string>>({});
const configScope = computed(() => props.module.path === '/settings' ? 'system' : 'store');
const saveConfig = (key: string) => {
    if (!configValues.value[key]) {
return;
}

    router.post(`/configuration/${configScope.value}`, { key, value: configValues.value[key] }, { onSuccess: () => configValues.value[key] = '' });
};
const clearConfig = (key: string) => {
    if (confirm(`确认清空 ${key}？`)) {
router.delete(`/configuration/${configScope.value}/${key}`);
}
};
const handleAction = (action: string) => {
    if (props.module.path === '/reputation/risk-sync' && action.includes('分析')) {
        router.post('/reputation/analyze');

        return;
    }

    refreshed.value = true;
    window.setTimeout(() => refreshed.value = false, 1800);

    if (action.includes('生成')) {
report.value = `舆情周报\n\n本周各平台舆情整体平稳，暂无新增高风险事项。\n数据生成时间：${new Date().toLocaleString('zh-CN')}`;
}
};
const saveReport = () => {
    if (!report.value) {
return;
}

    router.post('/reputation/weekly-report', { reporter: reporter.value, reportTo: reportTo.value, content: report.value });
};
</script>

<template>
    <Head :title="module.title" />
    <div class="deco-page">
        <div class="deco-header">
            <div>
                <h1 class="deco-title">{{ module.title }}</h1>
                <p class="deco-subtitle">{{ blueprint.subtitle || module.description }}</p>
            </div>
            <div class="deco-actions">
                <input v-if="!['ai','student','plugin','settings'].includes(blueprint.kind || '')" class="deco-input deco-date" value="2026-08-05　-　2026-08-11" aria-label="日期范围" />
                <button v-for="action in blueprint.actions" :key="action" class="deco-button" :class="{ primary: action.includes('刷新') || action.includes('分析') }" @click="action.includes('新增评论')?openRecord('review'):action.includes('新增帖子')?openRecord('reddit'):handleAction(action)">{{ action }}</button>
                <span v-if="refreshed" class="deco-pill green">操作成功</span>
            </div>
        </div>

        <template v-if="blueprint.kind === 'ai'">
            <div class="grid min-h-[calc(100vh-116px)] grid-cols-[280px_1fr] overflow-hidden border border-[#343941] bg-[#15191d]">
                <aside class="border-r border-[#343941] bg-[#252525] p-4">
                    <h2 class="deco-card-title">渠道评分</h2>
                    <div v-for="(score, name) in {Facebook:30,Google:30,TikTok:30,Bing:30,Criteo:30,SEO:74,GEO:68}" :key="name" class="mb-3 grid grid-cols-[70px_1fr_28px] items-center gap-2 text-xs">
                        <span>{{ name }}</span><span class="h-1.5 overflow-hidden rounded bg-[#35383b]"><i class="block h-full rounded bg-blue-500" :style="{width: score+'%'}"></i></span><b class="text-red-400">{{ score }}</b>
                    </div>
                    <h2 class="deco-card-title mt-7">历史分析</h2><p class="text-xs text-[#929aa4]">暂无历史记录</p>
                    <button class="deco-button mt-8 w-full">+ 新建对话</button>
                </aside>
                <main class="relative flex flex-col p-5">
                    <div class="flex items-center justify-between border-b border-[#30363d] pb-4"><b>AI 营销大脑</b><div class="deco-tabs !m-0 !border-0 !p-0"><button v-for="(tab,index) in blueprint.tabs" :key="tab" class="deco-tab" :class="{active:index===activeTab}" @click="activeTab=index">{{ tab }}</button></div></div>
                    <div class="flex flex-1 flex-col items-center justify-center text-center"><div class="mb-5 grid size-14 place-items-center rounded-2xl bg-blue-500/15 text-2xl">✦</div><h2 class="text-xl font-bold">上午好，让我们看看数据表现如何</h2><p class="mt-3 text-sm text-[#929aa4]">直接输入问题开始对话，AI 会自动为你创建会话</p></div>
                    <div class="rounded-2xl border border-[#454b53] p-4"><textarea class="min-h-16 w-full resize-none bg-transparent text-sm outline-none" placeholder="输入你的营销问题，开始对话..."></textarea><div class="flex flex-wrap gap-2"><button v-for="prompt in ['Facebook投放健康检查','Google Ads起量策略','TikTok冷启动方案','电商选品数据分析','亚马逊TACOS优化','跨渠道预算再分配','竞品广告策略拆解','品牌舆情风险扫描']" :key="prompt" class="rounded-full bg-[#303236] px-3 py-1.5 text-xs text-[#b9c0c8]">✦ {{ prompt }}</button></div></div>
                </main>
            </div>
        </template>

        <template v-else-if="blueprint.kind === 'student'">
            <div class="mb-4 flex justify-end"><div class="deco-card flex items-center gap-4 !p-3"><div><b class="text-sm">店铺展示</b><p class="text-xs text-[#929aa4]">{{ enabled ? '已启用' : '已停用' }}</p></div><button class="h-6 w-11 rounded-full p-1" :class="enabled ? 'bg-blue-500' : 'bg-[#4a4d52]'" @click="enabled=!enabled"><i class="block size-4 rounded-full bg-white transition" :class="{'translate-x-5':enabled}"></i></button><button class="deco-button primary" @click="saveStudentDiscount">保存</button></div></div>
            <div class="deco-metrics"><article v-for="(metric,index) in blueprint.metrics" :key="metric.label" class="deco-card deco-metric"><p class="deco-metric-label">{{ metric.label }}</p><p class="deco-metric-value">{{ index===0?(studentDiscount?.issued||0):index===1?(studentDiscount?.failed||0):(studentDiscount?.total||0) }}</p></article></div>
            <div class="deco-tabs rounded-lg border border-[#353a40] bg-[#252525] !p-2"><button v-for="(tab,index) in blueprint.tabs" :key="tab" class="deco-tab" :class="{active:index===activeTab}" @click="activeTab=index">{{ tab }}</button></div>
            <div v-if="!enabled" class="deco-card mb-4 !border-amber-700 !bg-[#2a2418]"><h2 class="deco-card-title text-amber-400">该应用已停用</h2><p class="text-sm text-[#b4abb0]">请先启用应用并保存，然后再发布学生优惠页面。</p></div>
            <div class="deco-card"><h2 class="deco-card-title">设置指南</h2><div v-for="(step,index) in ['启用应用','选择验证方式','创建并设计学生优惠活动','添加学生优惠页面模板']" :key="step" class="flex items-center gap-3 border-b border-[#393d42] py-4 last:border-0"><span class="grid size-6 place-items-center rounded-full" :class="index===0&&!enabled?'border border-[#464b51]':'bg-emerald-500 text-white'">{{ index===0&&!enabled?'': '✓' }}</span><span>{{ step }}</span><span class="ml-auto">›</span></div></div>
        </template>

        <template v-else-if="blueprint.kind === 'risk'">
            <section class="deco-card mb-4"><div class="flex items-center gap-3"><h2 class="deco-card-title !mb-0">🤖 AI 自动识别风险</h2><button class="deco-button primary" @click="handleAction('立即分析')">🔍 立即分析</button></div><div class="deco-empty">点击「立即分析」基于数据库数据自动检测风险</div></section>
            <section class="deco-card mb-4"><div class="flex items-center justify-between"><h2 class="deco-card-title !mb-0">🚨 风险清单（{{ records?.risks?.length || 0 }} 项）</h2><button class="deco-button primary" @click="openRecord('risk')">+ 添加风险</button></div><div v-if="!records?.risks?.length" class="deco-empty">✅ 暂无风险项，舆情整体平稳</div><div v-else class="mt-4 space-y-2"><article v-for="risk in records.risks" :key="risk.id" class="rounded bg-[#2d3034] p-3 text-sm"><span class="deco-pill red">{{risk.level}}</span>　{{risk.description}}</article></div></section>
            <section class="deco-card mb-4"><div class="flex items-center justify-between"><h2 class="deco-card-title !mb-0">📋 资源支持需求（{{ records?.resources?.length || 0 }} 项）</h2><button class="deco-button primary" @click="openRecord('resource')">+ 添加需求</button></div><div v-if="!records?.resources?.length" class="deco-empty">暂无资源支持需求</div><div v-else class="mt-4 space-y-2"><article v-for="item in records.resources" :key="item.id" class="rounded bg-[#2d3034] p-3 text-sm">{{item.description}}</article></div></section>
        </template>

        <template v-else-if="blueprint.kind === 'report'">
            <section class="deco-card mb-4"><div class="grid gap-3 md:grid-cols-[1fr_1fr_1fr_auto]"><input class="deco-input" placeholder="汇报周期" value="本周" disabled><input v-model="reporter" class="deco-input" placeholder="输入汇报人"><input v-model="reportTo" class="deco-input" placeholder="输入汇报对象"><button class="deco-button primary" @click="handleAction('生成')">📄 一键生成周报</button></div></section>
            <section class="deco-card min-h-72"><div class="flex items-center justify-between"><h2 class="deco-card-title">📝 周报内容</h2><button v-if="report" class="deco-button primary" @click="saveReport">保存本周周报</button></div><textarea v-if="report" v-model="report" class="deco-input min-h-52 w-full py-3 font-mono text-sm leading-7"></textarea><div v-else class="deco-empty">点击「一键生成周报」自动汇总各平台数据</div></section>
            <section v-if="Array.isArray(records) && records.length" class="deco-card mt-4"><h2 class="deco-card-title">历史归档</h2><article v-for="item in records" :key="item.id" class="flex items-center justify-between border-b border-[#3a3f45] py-3 last:border-0"><div><b>{{ item.year }} 年第 {{ item.week }} 周</b><p class="mt-1 text-xs text-[#929aa4]">{{ item.reporter || '未填写汇报人' }} → {{ item.reportTo || '未填写汇报对象' }}</p></div><span class="deco-pill green">已保存</span></article></section>
        </template>

        <template v-else-if="blueprint.kind === 'plugin'">
            <div class="deco-metrics"><article v-for="(metric,index) in blueprint.metrics" :key="metric.label" class="deco-card deco-metric"><p class="deco-metric-label">{{ metric.label }}</p><p class="deco-metric-value">{{ index===0||index===2?1:0 }}</p></article></div>
            <div class="grid max-w-3xl gap-4 md:grid-cols-2"><section class="deco-card"><div class="mb-4 flex items-start justify-between"><span class="grid size-12 place-items-center rounded-lg bg-blue-500/20 text-2xl">🎓</span><span class="deco-pill">待配置</span></div><h2 class="text-xl font-bold">学生优惠 <small class="text-xs font-normal text-[#929aa4]">v1.0.0</small></h2><p class="mt-2 text-sm text-blue-400">转化工具</p><p class="my-5 text-sm leading-6 text-[#bdc3cb]">教育邮箱自动发放优惠码；其他邮箱上传学生证，由后台人工审核。</p><button class="deco-button primary w-full">开始配置</button></section><section class="grid min-h-72 place-items-center rounded-lg border border-dashed border-[#454b53] text-center"><div><div class="text-4xl text-[#9ba3ad]">＋</div><h2 class="mt-4 text-lg font-bold">后续插件统一加入这里</h2><p class="mt-2 text-sm text-[#929aa4]">登记插件信息后，管理卡片会自动显示。</p></div></section></div>
        </template>

        <template v-else-if="blueprint.kind === 'settings'">
            <div v-if="blueprint.metrics" class="deco-metrics"><article v-for="metric in blueprint.metrics" :key="metric.label" class="deco-card deco-metric"><p class="deco-metric-label">{{ metric.label }}</p><p class="deco-metric-value">{{ metric.value }}</p></article></div>
            <section class="deco-card">
                <div class="deco-tabs"><button v-for="(tab,index) in blueprint.tabs" :key="tab" class="deco-tab" :class="{active:index===activeTab}" @click="activeTab=index">{{ tab }}</button></div>
                <div class="grid gap-5 md:grid-cols-[220px_1fr]">
                    <nav class="space-y-1"><button v-for="(integration,index) in currentIntegrations" :key="integration.name" class="block w-full rounded px-3 py-3 text-left text-sm" :class="index === activeIntegration ? 'bg-blue-500/10 text-blue-400' : 'hover:bg-white/5'" @click="activeIntegration = index">{{ integration.name }}</button></nav>
                    <div class="border-l-2 border-blue-500 pl-5"><h2 class="text-lg font-bold">{{ selectedIntegration?.name }}</h2><p class="mt-1 text-sm text-[#929aa4]">{{ selectedIntegration?.hint || 'API 凭证用于安全地同步平台数据，密钥加密存储且不会在页面回显。' }}</p>
                        <div v-if="selectedIntegration?.keys.length" class="mt-6 space-y-3"><div v-for="key in selectedIntegration.keys" :key="key" class="rounded-lg bg-[#2c3034] p-4"><div class="flex items-center justify-between"><div><b class="text-sm">配置项</b><p class="mt-1 font-mono text-xs text-[#929aa4]">{{ key }}</p></div><span class="deco-pill" :class="configuration?.[key] ? 'green' : 'red'">{{ configuration?.[key] ? '已配置' : '未配置' }}</span></div><div class="mt-4 flex flex-wrap gap-2"><input v-model="configValues[key]" class="deco-input min-w-52 flex-1" :type="key.includes('SECRET') || key.includes('TOKEN') || key.includes('PASSWORD') || key.includes('API_KEY') ? 'password' : 'text'" :placeholder="configuration?.[key] ? '已配置，输入新值以替换' : '未配置，输入以设置'"><button class="deco-button primary" @click="saveConfig(key)">保存</button><button v-if="configuration?.[key]" class="deco-button text-red-400" @click="clearConfig(key)">清空</button></div></div></div>
                    </div>
                </div>
            </section>
        </template>

        <template v-else>
            <div v-if="blueprint.alert" class="deco-alert" :class="{warning:blueprint.alertTone==='warning'}">🔴 {{ blueprint.alert }}</div>
            <div v-if="blueprint.tabs?.length" class="deco-tabs"><button v-for="(tab,index) in blueprint.tabs" :key="tab" class="deco-tab" :class="{active:index===activeTab}" @click="activeTab=index">{{ tab }}</button></div>
            <div v-if="blueprint.metrics?.length" class="deco-metrics" :class="{five:blueprint.metrics.length===5,six:blueprint.metrics.length===6}"><article v-for="metric in blueprint.metrics" :key="metric.label" class="deco-card deco-metric"><p class="deco-metric-label">{{ metric.label }}</p><p class="deco-metric-value">{{ metric.value }}</p><p v-if="metric.detail" class="deco-metric-detail">{{ metric.detail }}</p></article></div>
            <div v-if="blueprint.panels?.length" class="deco-grid-2"><section v-for="(panel,index) in blueprint.panels" :key="panel" class="deco-card deco-panel"><h2 class="deco-card-title">{{ panel }}</h2><div v-if="index<2 && blueprint.kind!=='simple'" class="deco-chart-bars"><span v-for="height in [24,48,36,72,45,62,84,54]" :key="height" :style="{height:height+'%'}"></span></div><div v-else class="deco-empty">暂无数据</div></section></div>
            <div v-if="blueprint.tableHeaders" class="deco-table-wrap"><table class="deco-table"><thead><tr><th v-for="heading in blueprint.tableHeaders" :key="heading">{{ heading }}</th></tr></thead><tbody><tr><td :colspan="blueprint.tableHeaders.length" class="!py-16 text-center text-[#929aa4]">暂无数据</td></tr></tbody></table></div>
            <section v-if="Array.isArray(records) && records.length" class="deco-card mt-4"><h2 class="deco-card-title">最新记录</h2><article v-for="record in records" :key="record.id" class="border-b border-[#3a3f45] py-3 last:border-0"><div class="flex items-center justify-between"><b>{{record.title || record.platform}}</b><span class="deco-pill">{{record.star ? record.star+'★' : record.sentiment}}</span></div><p class="mt-2 text-sm text-[#b7bec7]">{{record.content || record.body}}</p></article></section>
        </template>

        <div v-if="recordModal" class="fixed inset-0 z-[90] grid place-items-center bg-black/70 p-4" @click.self="recordModal=false"><form class="deco-card w-full max-w-lg" @submit.prevent="submitRecord"><div class="flex items-center justify-between"><h2 class="deco-card-title">新增{{recordEntity==='review'?'评论':recordEntity==='reddit'?'帖子':recordEntity==='risk'?'风险':'资源需求'}}</h2><button type="button" @click="recordModal=false">×</button></div><div class="grid gap-3"><input v-if="recordEntity==='reddit'" v-model="recordTitle" class="deco-input" placeholder="帖子标题" required><select v-if="recordEntity==='review'" v-model="recordStar" class="deco-input"><option v-for="star in [5,4,3,2,1]" :key="star" :value="star">{{star}} 星</option></select><textarea v-model="recordContent" class="deco-input min-h-32 py-3" placeholder="请输入内容" required></textarea><button class="deco-button primary">保存记录</button></div></form></div>
    </div>
</template>
