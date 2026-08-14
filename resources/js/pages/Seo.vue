<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type GscDay = { date: string; clicks: number; impressions: number; ctr: number; position: number };
type GaDay = { date: string; sessions: number; activeUsers: number; revenue: number };
type Overview = {
    gsc: { summary: { clicks: number; impressions: number; ctr: number; position: number }; daily: GscDay[] } | null;
    ga4: { summary: { sessions: number; activeUsers: number; revenue: number }; daily: GaDay[] } | null;
    errors: Record<string, string>;
};
type GoalMetric = { id: string; name: string; category: string; unit: string; source: string; current: number; target: number; completion: number; expected: number; forecast: number; forecast_rate: number; status: string };
type Goals = { month: string; dataThrough: string | null; metrics: GoalMetric[]; onTrack: number; atRisk: number };

const props = defineProps<{
    initialData?: Overview | null;
    initialRange?: { startDate: string; endDate: string } | null;
    goals?: Goals | null;
    dataSync?: { syncedAt: string | null; lastError: string | null } | null;
}>();

const yesterday = new Date(Date.now() - 86400000).toISOString().slice(0, 10);
const eightDaysAgo = new Date(Date.now() - 8 * 86400000).toISOString().slice(0, 10);
const startDate = ref(props.initialRange?.startDate || eightDaysAgo);
const endDate = ref(props.initialRange?.endDate || yesterday);
const activeTab = ref<'goals' | 'overview' | 'gsc' | 'ga4'>('goals');
const loading = ref(false);

const refresh = () => {
    loading.value = true;
    router.post('/organic/seo/refresh', { startDate: startDate.value, endDate: endDate.value, month: props.goals?.month }, {
        preserveScroll: true,
        onFinish: () => { loading.value = false; },
    });
};

const data = computed(() => props.initialData || { gsc: null, ga4: null, errors: {} });
const metrics = computed(() => [
    { label: 'GSC 点击', value: Math.round(data.value.gsc?.summary.clicks || 0).toLocaleString(), detail: 'Google 自然搜索点击' },
    { label: 'GSC 展示', value: Math.round(data.value.gsc?.summary.impressions || 0).toLocaleString(), detail: `CTR ${((data.value.gsc?.summary.ctr || 0) * 100).toFixed(2)}%` },
    { label: 'GA4 Sessions', value: (data.value.ga4?.summary.sessions || 0).toLocaleString(), detail: `${(data.value.ga4?.summary.activeUsers || 0).toLocaleString()} 活跃用户` },
    { label: 'GA4 Revenue', value: `$${(data.value.ga4?.summary.revenue || 0).toLocaleString(undefined, { maximumFractionDigits: 2 })}`, detail: `${startDate.value} 至 ${endDate.value}` },
]);
const maxGsc = computed(() => Math.max(1, ...(data.value.gsc?.daily || []).map((item) => item.clicks)));
const maxGa = computed(() => Math.max(1, ...(data.value.ga4?.daily || []).map((item) => item.sessions)));
const overall = computed(() => props.goals?.atRisk ? '需追赶' : '节奏正常');
const formatValue = (metric: GoalMetric, value: number) => metric.unit === '$'
    ? `$${Math.round(value).toLocaleString()}`
    : `${Math.round(value).toLocaleString()}${metric.unit}`;
</script>

<template>
    <Head title="SEO / GEO" />
    <div class="deco-page">
        <header class="deco-header">
            <div><h1 class="deco-title">SEO / GEO 分析</h1><p class="deco-subtitle">月度目标进度 · 综合分析 · GSC / GA 源数据</p></div>
            <div class="deco-actions">
                <input v-model="startDate" type="date" class="deco-input" aria-label="开始日期">
                <span>—</span>
                <input v-model="endDate" type="date" class="deco-input" aria-label="结束日期">
                <button class="deco-button primary" :disabled="loading" @click="refresh">{{ loading ? '正在更新…' : '刷新最新数据' }}</button>
            </div>
        </header>

        <div class="deco-tabs">
            <button class="deco-tab" :class="{active:activeTab==='goals'}" @click="activeTab='goals'">🎯 目标看板</button>
            <button class="deco-tab" :class="{active:activeTab==='overview'}" @click="activeTab='overview'">📈 综合看板</button>
            <button class="deco-tab" :class="{active:activeTab==='gsc'}" @click="activeTab='gsc'">🔍 GSC 源数据</button>
            <button class="deco-tab" :class="{active:activeTab==='ga4'}" @click="activeTab='ga4'">📊 GA 源数据</button>
        </div>

        <p v-if="dataSync?.syncedAt" class="mb-4 text-right text-xs text-[#929aa4]">最新更新：{{ new Date(dataSync.syncedAt).toLocaleString('zh-CN') }}｜数据截至：{{ goals?.dataThrough || endDate }}</p>
        <div v-if="dataSync?.lastError" class="deco-alert warning">{{ dataSync.lastError }}</div>
        <div v-if="Object.keys(data.errors || {}).length" class="deco-alert warning">部分数据源异常：{{ Object.entries(data.errors).map(([key,value]) => `${key}: ${value}`).join('；') }}</div>

        <template v-if="activeTab==='goals'">
            <div class="grid gap-4 md:grid-cols-4">
                <article class="deco-card deco-metric"><p class="deco-metric-label">总体判断</p><p class="deco-metric-value">{{ overall }}</p><p class="deco-metric-detail">按当前完成进度综合判断</p></article>
                <article class="deco-card deco-metric"><p class="deco-metric-label">预计达标</p><p class="deco-metric-value !text-emerald-500">{{ goals?.onTrack || 0 }}/{{ goals?.metrics.length || 0 }}</p><p class="deco-metric-detail">月底预计可达标数</p></article>
                <article class="deco-card deco-metric"><p class="deco-metric-label">需要追赶</p><p class="deco-metric-value !text-orange-500">{{ goals?.atRisk || 0 }}</p><p class="deco-metric-detail">低于进度线且预计不达标</p></article>
                <article class="deco-card deco-metric"><p class="deco-metric-label">数据范围</p><p class="deco-metric-value !text-violet-500">{{ goals?.dataThrough?.slice(5) || '—' }}</p><p class="deco-metric-detail">最新可用数据日期</p></article>
            </div>
            <section class="mt-5">
                <div class="mb-3"><h2 class="text-xl font-bold">核心目标进度</h2><p class="text-sm text-[#929aa4]">{{ goals?.month?.replace('-', ' 年 ') }} 月目标</p></div>
                <div class="grid gap-4 xl:grid-cols-2">
                    <article v-for="metric in goals?.metrics || []" :key="metric.id" class="deco-card">
                        <div class="flex items-start justify-between gap-4"><div><h3 class="font-bold">{{ metric.name }}</h3><p class="mt-1 text-xs text-[#929aa4]">{{ metric.source }}</p></div><span class="deco-pill" :class="metric.forecast_rate >= 1 ? 'green' : 'red'">{{ metric.status }}</span></div>
                        <div class="mt-5 flex items-end gap-2"><b class="text-2xl">{{ formatValue(metric, metric.current) }}</b><span class="pb-1 text-[#929aa4]">/ {{ formatValue(metric, metric.target) }}</span></div>
                        <div class="mt-3 h-2 overflow-hidden rounded bg-[#353a40]"><i class="block h-full bg-blue-500" :style="{width:`${Math.min(100,metric.completion*100)}%`}"></i></div>
                        <div class="mt-3 grid grid-cols-3 text-xs"><span>完成度<br><b>{{ (metric.completion*100).toFixed(1) }}%</b></span><span>应完成<br><b>{{ formatValue(metric, metric.expected) }}</b></span><span>月底预估<br><b>{{ formatValue(metric, metric.forecast) }}</b></span></div>
                    </article>
                </div>
            </section>
        </template>

        <template v-else-if="activeTab==='overview'">
            <div class="deco-metrics">
                <article v-for="metric in metrics" :key="metric.label" class="deco-card deco-metric"><p class="deco-metric-label">{{ metric.label }}</p><p class="deco-metric-value">{{ metric.value }}</p><p class="deco-metric-detail">{{ metric.detail }}</p></article>
            </div>
            <div class="deco-grid-2">
                <section class="deco-card deco-panel"><h2 class="deco-card-title">每日自然搜索点击</h2><div class="deco-chart-bars"><span v-for="item in data.gsc?.daily || []" :key="item.date" :title="`${item.date}: ${item.clicks}`" :style="{height:`${Math.max(4,item.clicks/maxGsc*100)}%`}"></span></div></section>
                <section class="deco-card deco-panel"><h2 class="deco-card-title">每日 GA4 Sessions</h2><div class="deco-chart-bars"><span v-for="item in data.ga4?.daily || []" :key="item.date" :title="`${item.date}: ${item.sessions}`" :style="{height:`${Math.max(4,item.sessions/maxGa*100)}%`}"></span></div></section>
            </div>
        </template>

        <div v-else class="deco-table-wrap">
            <table v-if="activeTab==='gsc'" class="deco-table"><thead><tr><th>日期</th><th>点击</th><th>展示</th><th>CTR</th><th>平均排名</th></tr></thead><tbody><tr v-for="item in data.gsc?.daily || []" :key="item.date"><td>{{ item.date }}</td><td>{{ item.clicks }}</td><td>{{ item.impressions }}</td><td>{{ (item.ctr*100).toFixed(2) }}%</td><td>{{ item.position.toFixed(1) }}</td></tr></tbody></table>
            <table v-else class="deco-table"><thead><tr><th>日期</th><th>Sessions</th><th>活跃用户</th><th>Revenue</th></tr></thead><tbody><tr v-for="item in data.ga4?.daily || []" :key="item.date"><td>{{ item.date }}</td><td>{{ item.sessions }}</td><td>{{ item.activeUsers }}</td><td>${{ item.revenue.toFixed(2) }}</td></tr></tbody></table>
        </div>
    </div>
</template>
