<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
type Row = Record<string, any>;
const props = defineProps<{
    module: { path: string; title: string; description: string };
    analytics?: {
        metrics?: Array<{ label: string; value: string }>;
        rows?: Row[];
        trend?: number[];
        error?: string;
    };
    externalSync?: { syncedAt: string | null; lastError: string | null } | null;
}>();
const active = ref(0),
    syncing = ref(false);
const metrics = computed(() => props.analytics?.metrics || []),
    rows = computed(() => props.analytics?.rows || []),
    trend = computed(() => props.analytics?.trend || []);
const platform = computed(() => props.module.path.split('/').pop() || '');
const tabMap: Record<string, string[]> = {
    facebook: [
        '📊 总览',
        '📈 趋势分析',
        '📋 广告系列',
        '🎨 优质素材',
        '✍️ 优质文案',
        '💡 AI 分析',
    ],
    google: [
        '📊 总览',
        '📈 趋势分析',
        '📋 广告系列',
        '🔍 搜索词',
        '🔑 关键词',
        '📅 周报',
        '🎯 目标',
        '💡 AI 分析',
    ],
    tiktok: [
        '📊 总览',
        '📈 趋势分析',
        '📋 广告系列',
        '🎬 优质素材',
        '💡 AI 分析',
    ],
    bing: ['📊 总览', '📈 趋势分析', '📋 广告系列', '🎯 AI 分析'],
    criteo: ['📊 总览', '📈 趋势分析', '📋 广告系列'],
};
const tabs = computed(() => tabMap[platform.value] || ['📊 总览']);
const refresh = () => {
    syncing.value = true;
    router.post(
        '/data-sync/refresh',
        { path: props.module.path },
        { preserveScroll: true, onFinish: () => (syncing.value = false) },
    );
};
const val = (r: Row, ...keys: string[]) => {
    for (const key of keys) {
        const camel = key.replace(/_([a-z])/g, (_, c) => c.toUpperCase());
        const value =
            r[key] ??
            r.metrics?.[key] ??
            r.metrics?.[camel] ??
            r.segments?.[key] ??
            r.segments?.[camel] ??
            r.dimensions?.[key] ??
            r.dimensions?.[camel] ??
            r.campaign?.[key] ??
            r.campaign?.[camel];

        if (value !== undefined && value !== '') {
            return value;
        }
    }

    return '—';
};
const rowName = (r: Row, index: number) =>
    val(r, 'campaign_name', 'name', 'campaign_id', 'id') === '—'
        ? `记录 ${index + 1}`
        : val(r, 'campaign_name', 'name', 'campaign_id', 'id');
</script>
<template>
    <Head :title="module.title" />
    <div class="deco-page">
        <div class="deco-header">
            <div>
                <h1 class="deco-title">{{ module.title }}</h1>
                <p class="deco-subtitle">{{ module.description }}</p>
            </div>
            <div class="deco-actions">
                <span
                    v-if="externalSync?.syncedAt"
                    class="text-xs text-[#929aa4]"
                    >最近同步：{{
                        new Date(externalSync.syncedAt).toLocaleString('zh-CN')
                    }}</span
                ><button
                    class="deco-button primary"
                    :disabled="syncing"
                    @click="refresh"
                >
                    {{ syncing ? '同步中…' : '刷新' }}
                </button>
            </div>
        </div>
        <div
            v-if="analytics?.error || externalSync?.lastError"
            class="deco-alert"
        >
            {{ analytics?.error || externalSync?.lastError }}
        </div>
        <div class="deco-tabs">
            <button
                v-for="(tab, index) in tabs"
                :key="tab"
                class="deco-tab"
                :class="{ active: active === index }"
                @click="active = index"
            >
                {{ tab }}
            </button>
        </div>
        <template v-if="active === 0"
            ><div
                v-if="metrics.length"
                class="deco-metrics"
                :class="{
                    five: metrics.length === 5,
                    six: metrics.length === 6,
                }"
            >
                <article
                    v-for="metric in metrics"
                    :key="metric.label"
                    class="deco-card deco-metric"
                >
                    <p class="deco-metric-label">{{ metric.label }}</p>
                    <p class="deco-metric-value">{{ metric.value }}</p>
                </article>
            </div>
            <div v-else class="deco-alert warning">
                当前日期范围没有可用广告报表。点击刷新将重新请求平台并写入
                MySQL。
            </div>
            <div class="deco-grid-2">
                <section class="deco-card min-h-[320px]">
                    <h2 class="deco-card-title">花费与转化趋势</h2>
                    <div v-if="trend.length" class="deco-chart-bars !h-64">
                        <span
                            v-for="(v, i) in trend"
                            :key="i"
                            :style="{
                                height:
                                    Math.max(
                                        3,
                                        (v * 100) / Math.max(...trend, 1),
                                    ) + '%',
                            }"
                        ></span>
                    </div>
                    <div v-else class="deco-empty">暂无趋势数据</div>
                </section>
                <section class="deco-card min-h-[320px]">
                    <h2 class="deco-card-title">转化漏斗</h2>
                    <div
                        v-for="(m, i) in metrics.slice().reverse()"
                        :key="m.label"
                        class="mx-auto mb-3 grid h-12 place-items-center bg-blue-500/70 text-sm font-bold"
                        :style="{ width: 45 + i * 10 + '%' }"
                    >
                        {{ m.label }}　{{ m.value }}
                    </div>
                </section>
            </div></template
        >
        <section v-else-if="active === 1" class="deco-card">
            <h2 class="deco-card-title">每日趋势分析</h2>
            <div v-if="trend.length" class="deco-chart-bars !h-80">
                <span
                    v-for="(v, i) in trend"
                    :key="i"
                    :style="{
                        height:
                            Math.max(3, (v * 100) / Math.max(...trend, 1)) +
                            '%',
                    }"
                ></span>
            </div>
            <div v-else class="deco-empty big">暂无趋势数据</div>
        </section>
        <section v-else-if="active === 2" class="deco-card">
            <h2 class="deco-card-title">广告系列 / 每日报表</h2>
            <div v-if="rows.length" class="deco-table-wrap border-0">
                <table class="deco-table">
                    <thead>
                        <tr>
                            <th>广告系列</th>
                            <th>日期</th>
                            <th>花费</th>
                            <th>展示</th>
                            <th>点击</th>
                            <th>转化</th>
                            <th>收入/价值</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(r, i) in rows" :key="i">
                            <td>{{ rowName(r, i) }}</td>
                            <td>
                                {{
                                    val(
                                        r,
                                        'date_start',
                                        'stat_time_day',
                                        'date',
                                        'TimePeriod',
                                        '﻿\"TimePeriod\"',
                                        'Day',
                                    )
                                }}
                            </td>
                            <td>
                                {{
                                    val(
                                        r,
                                        'spend',
                                        'cost_micros',
                                        'Spend',
                                        'AdvertiserCost',
                                    )
                                }}
                            </td>
                            <td>
                                {{
                                    val(
                                        r,
                                        'impressions',
                                        'Impressions',
                                        'Displays',
                                    )
                                }}
                            </td>
                            <td>{{ val(r, 'clicks', 'Clicks') }}</td>
                            <td>
                                {{
                                    val(
                                        r,
                                        'conversion',
                                        'conversions',
                                        'Conversions',
                                        'SalesPc7d',
                                    )
                                }}
                            </td>
                            <td>
                                {{
                                    val(
                                        r,
                                        'Revenue',
                                        'conversions_value',
                                        'RevenueGeneratedPc7d',
                                    )
                                }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-else class="deco-empty big">
                当前快照只有汇总指标；刷新后将保存每日明细
            </div>
        </section>
        <section v-else-if="tabs[active].includes('AI')" class="deco-card">
            <h2 class="deco-card-title">{{ tabs[active] }}</h2>
            <div class="grid gap-3 md:grid-cols-2">
                <article
                    v-for="metric in metrics"
                    :key="metric.label"
                    class="rounded-lg bg-[#2c3034] p-4"
                >
                    <p class="text-xs text-[#929aa4]">{{ metric.label }}</p>
                    <b class="mt-2 block text-xl text-blue-400">{{
                        metric.value
                    }}</b>
                </article>
            </div>
            <p class="mt-5 text-sm leading-7 text-[#b7bec7]">
                分析基于最近一次写入 MySQL
                的平台报表。优先复核高花费、低转化广告系列，并在平台后台确认归因窗口后再调整预算。
            </p>
        </section>
        <section v-else class="deco-card">
            <h2 class="deco-card-title">{{ tabs[active] }}</h2>
            <div
                v-if="rows.length"
                class="grid gap-3 md:grid-cols-2 xl:grid-cols-3"
            >
                <article
                    v-for="(r, i) in rows.slice(0, 18)"
                    :key="i"
                    class="rounded-lg bg-[#2c3034] p-4"
                >
                    <b>{{ rowName(r, i) }}</b>
                    <p class="mt-3 text-xs text-[#aeb5bd]">
                        花费
                        {{
                            val(
                                r,
                                'spend',
                                'cost_micros',
                                'Spend',
                                'AdvertiserCost',
                            )
                        }}　点击 {{ val(r, 'clicks', 'Clicks') }}　转化
                        {{
                            val(
                                r,
                                'conversion',
                                'conversions',
                                'Conversions',
                                'SalesPc7d',
                            )
                        }}
                    </p>
                </article>
            </div>
            <div v-else class="deco-empty big">当前快照暂无可展示明细</div>
        </section>
    </div>
</template>
