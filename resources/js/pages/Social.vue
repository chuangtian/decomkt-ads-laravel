<script setup lang="ts">
import PaginationControls, { type PagePayload } from '@/components/PaginationControls.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type Row = Record<string, any>;
type Summary = {
    posts: number;
    views: number;
    likes: number;
    comments: number;
    shares: number;
    platforms: Array<{ name: string; posts: number; views: number; eng: number; rate: number }>;
};

const props = defineProps<{ summary?: Summary | null; recordPage?: PagePayload<Row> | null }>();
const active = ref(0);
const platform = ref('全部');
const visibility = ref('可见帖子');
const loading = ref(false);
const rows = computed(() => props.recordPage?.data ?? []);
const totals = computed<Summary>(() => props.summary ?? { posts: 0, views: 0, likes: 0, comments: 0, shares: 0, platforms: [] });
const fmt = (value: number) => value >= 1e6 ? `${(value / 1e6).toFixed(1)}M` : value >= 1e3 ? `${(value / 1e3).toFixed(1)}K` : String(Math.round(value));

const loadPage = (page: number, nextPlatform = platform.value) => {
    loading.value = true;
    platform.value = nextPlatform;
    router.get('/organic/social', {
        page,
        platform: nextPlatform === '全部' ? undefined : nextPlatform,
    }, {
        only: ['recordPage'],
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onFinish: () => loading.value = false,
    });
};
</script>

<template>
    <Head title="品牌官媒" />
    <div class="deco-page">
        <div class="deco-header">
            <div><h1 class="deco-title">每日官媒运营复盘台</h1><p class="deco-subtitle">以 Instagram 官媒为主，快速复盘每日发布与互动表现。</p></div>
        </div>
        <div class="deco-tabs">
            <button v-for="(tab, index) in ['平台拆解', '📝 每日复盘', '📋 周报', '🎯 AI 分析']" :key="tab" class="deco-tab" :class="{ active: active === index }" @click="active = index">{{ tab }}</button>
        </div>

        <template v-if="active === 0">
            <div class="mb-4 flex flex-wrap gap-2">
                <button v-for="item in ['全部', 'Instagram', 'Facebook', 'YouTube']" :key="item" class="deco-button" :class="{ primary: platform === item }" :disabled="loading" @click="loadPage(1, item)">{{ item }}</button>
                <button class="deco-button ml-auto">📥 导入 / 下载模板</button>
            </div>
            <div class="deco-metrics five">
                <article v-for="metric in [{ l: '帖子数', v: totals.posts }, { l: '浏览量', v: fmt(totals.views) }, { l: '点赞', v: fmt(totals.likes) }, { l: '评论', v: fmt(totals.comments) }, { l: '分享', v: fmt(totals.shares) }]" :key="metric.l" class="deco-card deco-metric">
                    <p class="deco-metric-label">{{ metric.l }}</p><p class="deco-metric-value">{{ metric.v }}</p>
                </article>
            </div>
            <div class="deco-grid-2">
                <section class="deco-card">
                    <h2 class="deco-card-title">品牌官媒漏斗</h2>
                    <div v-for="item in [{ l: '浏览', v: totals.views }, { l: '互动', v: totals.likes + totals.comments + totals.shares }, { l: '评论', v: totals.comments }]" :key="item.l" class="mb-5">
                        <div class="flex justify-between"><span>{{ item.l }}</span><b>{{ fmt(item.v) }}</b></div>
                        <div class="mt-2 h-5 bg-blue-500" :style="{ width: Math.max(3, item.v * 100 / Math.max(totals.views, 1)) + '%' }"></div>
                    </div>
                </section>
                <section class="deco-card">
                    <h2 class="deco-card-title">平台漏斗对比</h2>
                    <div v-for="item in totals.platforms" :key="item.name" class="mb-4 rounded bg-[#2c3034] p-4">
                        <div class="flex justify-between"><b class="capitalize">{{ item.name }}</b><span>互动率 {{ item.rate.toFixed(2) }}%</span></div>
                        <p class="mt-2 text-xs text-[#9ba3ad]">{{ item.posts }} 帖 · {{ fmt(item.views) }} 浏览 · {{ fmt(item.eng) }} 互动</p>
                    </div>
                </section>
            </div>
            <section class="deco-card">
                <div class="flex items-center justify-between"><h2 class="deco-card-title">帖子明细</h2><div class="flex gap-2"><button v-for="item in ['可见帖子', '已隐藏帖子']" :key="item" class="deco-button" :class="{ primary: visibility === item }" @click="visibility = item">{{ item }}</button></div></div>
                <p class="mb-3 text-xs text-[#929aa4]">当前显示 {{ rows.length }} 条，共 {{ recordPage?.total ?? 0 }} 条</p>
                <div class="deco-table-wrap border-0"><table class="deco-table"><thead><tr><th>平台</th><th>类型</th><th>内容</th><th>发布日期</th><th>浏览量</th><th>赞</th><th>评论</th><th>分享</th><th>ER</th></tr></thead><tbody>
                    <tr v-for="row in rows" :key="row.id"><td>{{ row.platform }}</td><td>{{ row.postType }}</td><td class="max-w-lg"><p class="line-clamp-2">{{ row.description }}</p></td><td>{{ row.publishedAt }}</td><td>{{ fmt(+row.views || 0) }}</td><td>{{ fmt(+row.likes || 0) }}</td><td>{{ row.comments }}</td><td>{{ row.shares }}</td><td>{{ row.views ? ((((+row.likes || 0) + (+row.comments || 0) + (+row.shares || 0)) * 100 / row.views).toFixed(1)) : 0 }}%</td></tr>
                </tbody></table></div>
                <PaginationControls :page="recordPage" :busy="loading" @change="loadPage" />
            </section>
        </template>
        <section v-else class="deco-card"><h2 class="deco-card-title">{{ ['', '每日复盘', '周报', 'AI 分析'][active] }}</h2><p class="text-sm leading-7 text-[#b6bdc6]">本页基于 {{ totals.posts }} 条数据库记录生成。</p></section>
    </div>
</template>
