<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import PaginationControls from '@/components/PaginationControls.vue';
import type { PagePayload } from '@/components/PaginationControls.vue';

type Row = Record<string, any>;
type Summary = {
    total: number;
    names: number;
    views: number;
    likes: number;
    comments: number;
    engagementRate: number;
    trend: Row[];
    top: Row[];
};
const props = defineProps<{
    summary?: Summary | null;
    recordPage?: PagePayload<Row> | null;
}>();
const tab = ref(0);
const syncing = ref(false);
const loading = ref(false);
const rows = computed(() => props.recordPage?.data ?? []);
const totals = computed<Summary>(
    () =>
        props.summary ?? {
            total: 0,
            names: 0,
            views: 0,
            likes: 0,
            comments: 0,
            engagementRate: 0,
            trend: [],
            top: [],
        },
);
const numberValue = (value: any) =>
    Number(String(value ?? 0).replace(/[,$%]/g, '')) || 0;
const fmt = (value: number) =>
    value >= 1e6
        ? `${(value / 1e6).toFixed(1)}M`
        : value >= 1e3
          ? `${(value / 1e3).toFixed(1)}K`
          : String(Math.round(value));
const refresh = () => {
    syncing.value = true;
    router.post(
        '/data-sync/refresh',
        { path: '/organic/kol' },
        { preserveScroll: true, onFinish: () => (syncing.value = false) },
    );
};
const loadPage = (page: number) => {
    loading.value = true;
    router.get(
        '/organic/kol',
        { page },
        {
            only: ['recordPage'],
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onFinish: () => (loading.value = false),
        },
    );
};
const creatorName = (row: Row) =>
    Array.isArray(row['红人title']) ? row['红人title'][0] : row['红人title'];
</script>

<template>
    <Head title="红人运营" />
    <div class="deco-page">
        <div class="deco-header">
            <div>
                <h1 class="deco-title">红人运营</h1>
                <p class="deco-subtitle">
                    数据来源：飞书多维表格 · {{ totals.total }} 条合作记录
                </p>
            </div>
            <button
                class="deco-button primary"
                :disabled="syncing"
                @click="refresh"
            >
                {{ syncing ? '同步中…' : '刷新' }}
            </button>
        </div>
        <div class="deco-tabs">
            <button
                v-for="(item, index) in [
                    '合作数据明细',
                    '资源库',
                    'AI 推荐',
                    '红人运营增长洞察',
                ]"
                :key="item"
                class="deco-tab"
                :class="{ active: tab === index }"
                @click="tab = index"
            >
                {{ item }}
            </button>
        </div>
        <template v-if="tab === 0">
            <div class="deco-metrics">
                <article
                    v-for="metric in [
                        { l: '合作红人数', v: totals.names },
                        { l: '总浏览量', v: fmt(totals.views) },
                        {
                            l: '均播',
                            v: fmt(
                                totals.total ? totals.views / totals.total : 0,
                            ),
                        },
                        {
                            l: '平均互动率',
                            v: totals.engagementRate.toFixed(2) + '%',
                        },
                    ]"
                    :key="metric.l"
                    class="deco-card deco-metric"
                >
                    <p class="deco-metric-label">{{ metric.l }}</p>
                    <p class="deco-metric-value">{{ metric.v }}</p>
                </article>
            </div>
            <div class="deco-grid-2">
                <section class="deco-card">
                    <h2 class="deco-card-title">发布日期浏览趋势</h2>
                    <div class="deco-chart-bars">
                        <span
                            v-for="(row, index) in [...totals.trend].reverse()"
                            :key="index"
                            :style="{
                                height:
                                    Math.max(
                                        3,
                                        (numberValue(row['浏览']) * 100) /
                                            Math.max(
                                                ...totals.trend.map((item) =>
                                                    numberValue(item['浏览']),
                                                ),
                                                1,
                                            ),
                                    ) + '%',
                            }"
                        ></span>
                    </div>
                </section>
                <section class="deco-card">
                    <h2 class="deco-card-title">红人浏览 TOP 10</h2>
                    <div
                        v-for="row in totals.top"
                        :key="row.record_id"
                        class="flex justify-between border-b border-[#383d43] py-2 text-sm"
                    >
                        <span>{{ creatorName(row) }}</span
                        ><b>{{ fmt(numberValue(row['浏览'])) }}</b>
                    </div>
                </section>
            </div>
            <section class="deco-card">
                <h2 class="deco-card-title">合作数据明细</h2>
                <div class="deco-table-wrap border-0">
                    <table class="deco-table">
                        <thead>
                            <tr>
                                <th>红人 title</th>
                                <th>合作价格</th>
                                <th>发布日期</th>
                                <th>均播</th>
                                <th>合作链接</th>
                                <th>赞</th>
                                <th>评</th>
                                <th>浏览</th>
                                <th>互动率</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in rows" :key="row.record_id">
                                <td>{{ creatorName(row) }}</td>
                                <td>
                                    {{
                                        Array.isArray(row['合作价格'])
                                            ? row['合作价格'].join(' + ')
                                            : row['合作价格']
                                    }}
                                </td>
                                <td>
                                    {{
                                        row['发布日期']
                                            ? new Date(
                                                  +row['发布日期'],
                                              ).toLocaleDateString('zh-CN')
                                            : ''
                                    }}
                                </td>
                                <td>{{ row['均播'] }}</td>
                                <td>
                                    <a
                                        class="text-blue-400"
                                        :href="row['合作链接']"
                                        target="_blank"
                                        >查看链接</a
                                    >
                                </td>
                                <td>{{ row['赞'] }}</td>
                                <td>{{ row['评'] }}</td>
                                <td>{{ row['浏览'] }}</td>
                                <td>
                                    {{
                                        (
                                            numberValue(row['互动率']) * 100
                                        ).toFixed(2)
                                    }}%
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <PaginationControls
                    :page="recordPage"
                    :busy="loading"
                    @change="loadPage"
                />
            </section>
        </template>
        <section v-else class="deco-card">
            <h2 class="deco-card-title">
                {{ ['', '红人资源库', 'AI 推荐', '红人运营增长洞察'][tab] }}
            </h2>
            <p class="text-sm text-[#b7bec7]">
                该模块已连接 {{ totals.total }} 条飞书合作记录。
            </p>
        </section>
    </div>
</template>
