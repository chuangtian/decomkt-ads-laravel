<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import PaginationControls from '@/components/PaginationControls.vue';
import type { PagePayload } from '@/components/PaginationControls.vue';

type Row = Record<string, any>;
type Summary = Record<string, any>;
const props = defineProps<{
    module: { path: string; title: string; description: string };
    summary?: Summary | null;
    recordPage?: PagePayload<Row> | null;
}>();
const reddit = computed(() => props.module.path === '/reputation/reddit');
const rows = computed(() => props.recordPage?.data ?? []);
const stats = computed(() => props.summary ?? {});
const total = computed(() => Number(stats.value.total ?? 0));
const positive = computed(() => Number(stats.value.positive ?? 0));
const negative = computed(() => Number(stats.value.negative ?? 0));
const average = computed(() => Number(stats.value.average ?? 0));
const active = ref(0);
const loading = ref(false);
const showAdd = ref(false);
const draftTitle = ref('');
const draftContent = ref('');
const draftStar = ref(5);
const analyzed = ref(false);
const fmt = (value: number) =>
    value >= 1000 ? `${(value / 1000).toFixed(1)}K` : value.toLocaleString();
const starRows = computed(() =>
    (stats.value.stars ?? []).map((item: Row) => ({
        ...item,
        pct: total.value
            ? Math.round((Number(item.count) * 100) / total.value)
            : 0,
    })),
);
const topicMax = computed(() =>
    Math.max(
        ...(stats.value.topics ?? []).map((item: Row) => Number(item.count)),
        1,
    ),
);
const loadPage = (page = 1) => {
    loading.value = true;
    router.get(
        props.module.path,
        { detail: 'records', page },
        {
            only: ['recordPage'],
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onFinish: () => (loading.value = false),
        },
    );
};
const selectTab = (value: number) => {
    active.value = value;

    if (value === 1 && !props.recordPage) {
        loadPage();
    }
};
const addRecord = () =>
    router.post(
        '/reputation/items',
        reddit.value
            ? {
                  entity: 'reddit',
                  platform: 'Reddit',
                  title: draftTitle.value,
                  content: draftContent.value,
                  sentiment: 'NEUTRAL',
              }
            : {
                  entity: 'review',
                  platform: props.module.path.includes('trustpilot')
                      ? 'TRUSTPILOT'
                      : props.module.path.includes('google')
                        ? 'GOOGLE'
                        : 'WEBSITE',
                  content: draftContent.value,
                  star: draftStar.value,
                  sentiment: 'NEUTRAL',
              },
        {
            preserveScroll: true,
            onSuccess: () => {
                showAdd.value = false;
                draftTitle.value = '';
                draftContent.value = '';
            },
        },
    );
</script>

<template>
    <Head :title="module.title" />
    <div class="deco-page">
        <div class="deco-header">
            <div>
                <h1 class="deco-title">{{ module.title }}</h1>
                <p class="deco-subtitle">{{ module.description }}</p>
            </div>
            <button class="deco-button primary" @click="showAdd = !showAdd">
                + 新增{{ reddit ? '帖子' : '评论' }}
            </button>
        </div>
        <div class="deco-tabs">
            <button
                class="deco-tab"
                :class="{ active: active === 0 }"
                @click="selectTab(0)"
            >
                📊 总览</button
            ><button
                class="deco-tab"
                :class="{ active: active === 1 }"
                @click="selectTab(1)"
            >
                💬 {{ reddit ? '帖子追踪' : '评价管理' }}</button
            ><button
                class="deco-tab"
                :class="{ active: active === 2 }"
                @click="selectTab(2)"
            >
                🎯 {{ reddit ? '运营清单' : 'AI 分析' }}
            </button>
        </div>
        <section v-if="showAdd" class="deco-card mb-4">
            <h2 class="deco-card-title">新增{{ reddit ? '帖子' : '评论' }}</h2>
            <input
                v-if="reddit"
                v-model="draftTitle"
                class="deco-input mb-3 w-full"
                placeholder="帖子标题"
            /><textarea
                v-model="draftContent"
                class="deco-input min-h-28 w-full"
                :placeholder="reddit ? '帖子内容' : '评论内容'"
            ></textarea>
            <div class="mt-3 flex items-center gap-3">
                <select v-if="!reddit" v-model="draftStar" class="deco-input">
                    <option v-for="number in 5" :key="number" :value="number">
                        {{ number }} 星
                    </option></select
                ><button
                    class="deco-button primary"
                    :disabled="!draftContent"
                    @click="addRecord"
                >
                    保存</button
                ><button class="deco-button" @click="showAdd = false">
                    取消
                </button>
            </div>
        </section>

        <template v-if="active === 0 && reddit">
            <div class="deco-metrics five">
                <article
                    v-for="metric in [
                        { l: '帖子总数', v: fmt(total) },
                        { l: '总 Upvotes', v: fmt(Number(stats.upvotes || 0)) },
                        { l: '总评论数', v: fmt(Number(stats.comments || 0)) },
                        {
                            l: '正面情绪',
                            v:
                                Math.round(
                                    (positive * 100) / Math.max(total, 1),
                                ) + '%',
                        },
                        {
                            l: '负面情绪',
                            v:
                                Math.round(
                                    (negative * 100) / Math.max(total, 1),
                                ) + '%',
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
                    <h2 class="deco-card-title">🔥 高频话题</h2>
                    <div
                        v-for="topic in stats.topics || []"
                        :key="topic.name"
                        class="mb-3"
                    >
                        <div class="flex justify-between text-sm">
                            <span>{{ topic.name }}</span
                            ><b>{{ topic.count }} 帖</b>
                        </div>
                        <div class="mt-1 h-2 rounded bg-[#34383d]">
                            <i
                                class="block h-full rounded bg-blue-500"
                                :style="{
                                    width:
                                        (Number(topic.count) * 100) / topicMax +
                                        '%',
                                }"
                            ></i>
                        </div>
                    </div>
                </section>
                <section class="deco-card">
                    <h2 class="deco-card-title">
                        版块监控（{{ (stats.subreddits || []).length }} 个版块）
                    </h2>
                    <div
                        v-for="item in stats.subreddits || []"
                        :key="item.name"
                        class="grid grid-cols-4 border-b border-[#383d43] py-3 text-sm"
                    >
                        <b>{{ item.name }}</b
                        ><span>{{ item.n }} 帖</span><span>⬆ {{ item.u }}</span
                        ><span>💬 {{ item.c }}</span>
                    </div>
                </section>
            </div>
            <section class="deco-card">
                <h2 class="deco-card-title">
                    🚨 负面帖子预警（{{ negative }} 条）
                </h2>
                <article
                    v-for="item in stats.negativeItems || []"
                    :key="item.id"
                    class="border-b border-[#383d43] py-4"
                >
                    <b>{{ item.title }}</b>
                    <p class="mt-2 line-clamp-2 text-sm text-[#aeb5bd]">
                        {{ item.body }}
                    </p>
                    <p class="mt-2 text-xs text-[#7f8995]">
                        {{ item.subreddit }}　⬆ {{ item.upvotes }}　💬
                        {{ item.commentCount }}
                    </p>
                </article>
            </section>
        </template>
        <template v-else-if="active === 0">
            <div class="deco-metrics five">
                <article
                    v-for="metric in [
                        { l: '评论总数', v: fmt(total) },
                        { l: '综合评分', v: average.toFixed(1) + '★' },
                        {
                            l: '正面占比',
                            v:
                                Math.round(
                                    (positive * 100) / Math.max(total, 1),
                                ) + '%',
                        },
                        {
                            l: '负面占比',
                            v:
                                Math.round(
                                    (negative * 100) / Math.max(total, 1),
                                ) + '%',
                        },
                        { l: '紧急待处理', v: negative },
                    ]"
                    :key="metric.l"
                    class="deco-card deco-metric"
                >
                    <p class="deco-metric-label">{{ metric.l }}</p>
                    <p class="deco-metric-value">{{ metric.v }}</p>
                </article>
            </div>
            <div class="deco-grid-3">
                <section class="deco-card">
                    <h2 class="deco-card-title">星级分布</h2>
                    <div class="mb-4 text-3xl font-bold text-amber-400">
                        {{ average.toFixed(1) }} ★
                    </div>
                    <div
                        v-for="item in starRows"
                        :key="item.star"
                        class="mb-3 grid grid-cols-[42px_1fr_70px] items-center gap-2 text-sm"
                    >
                        <span>{{ item.star }}★</span>
                        <div class="h-2 rounded bg-[#34383d]">
                            <i
                                class="block h-full rounded bg-amber-500"
                                :style="{ width: item.pct + '%' }"
                            ></i>
                        </div>
                        <span>{{ item.count }} / {{ item.pct }}%</span>
                    </div>
                </section>
                <section class="deco-card">
                    <h2 class="deco-card-title">SKU 评分对比</h2>
                    <div
                        v-for="item in stats.skus || []"
                        :key="item.name"
                        class="flex justify-between border-b border-[#383d43] py-3 text-sm"
                    >
                        <span>{{ item.name }}</span
                        ><span
                            >{{ item.count }} 条　<b class="text-amber-400"
                                >{{ Number(item.average).toFixed(1) }}★</b
                            ></span
                        >
                    </div>
                    <div v-if="!(stats.skus || []).length" class="deco-empty">
                        暂无 SKU 数据
                    </div>
                </section>
                <section class="deco-card">
                    <h2 class="deco-card-title">Top Mentions</h2>
                    <div
                        v-for="item in stats.tags || []"
                        :key="item.name"
                        class="flex justify-between border-b border-[#383d43] py-3 text-sm"
                    >
                        <span>{{ item.name }}</span
                        ><b>{{ item.count }}</b>
                    </div>
                    <div v-if="!(stats.tags || []).length" class="deco-empty">
                        暂无标签数据
                    </div>
                </section>
            </div>
            <section class="deco-card">
                <h2 class="deco-card-title">
                    🚨 紧急待处理（{{ negative }} 条 1-2★ 负面评价）
                </h2>
                <article
                    v-for="item in stats.negativeItems || []"
                    :key="item.id"
                    class="border-b border-[#383d43] py-4"
                >
                    <div class="flex justify-between">
                        <b class="text-amber-400"
                            >{{ '★'.repeat(Number(item.star) || 0)
                            }}{{ '☆'.repeat(5 - (Number(item.star) || 0)) }}</b
                        ><span class="text-xs text-[#8f98a3]">{{
                            String(item.publishDate || '').slice(0, 10)
                        }}</span>
                    </div>
                    <p class="mt-2 text-sm leading-6">{{ item.content }}</p>
                </article>
            </section>
        </template>
        <section v-else-if="active === 1" class="deco-card">
            <h2 class="deco-card-title">
                {{ reddit ? '帖子追踪' : '评价管理' }}（{{
                    recordPage?.total ?? total
                }}）
            </h2>
            <div v-if="loading && !recordPage" class="deco-empty">
                正在加载明细…
            </div>
            <template v-else
                ><article
                    v-for="item in rows"
                    :key="item.id"
                    class="border-b border-[#383d43] py-4"
                >
                    <div class="flex justify-between">
                        <b>{{
                            reddit ? item.title : item.sku || item.platform
                        }}</b
                        ><span class="text-xs text-[#929aa4]">{{
                            String(
                                item.postDate || item.publishDate || '',
                            ).slice(0, 10)
                        }}</span>
                    </div>
                    <p class="mt-2 text-sm text-[#b7bec7]">
                        {{ reddit ? item.body : item.content }}
                    </p>
                </article>
                <PaginationControls
                    :page="recordPage"
                    :busy="loading"
                    @change="loadPage"
            /></template>
        </section>
        <section v-else class="deco-card">
            <h2 class="deco-card-title">
                {{ reddit ? '运营清单' : 'AI 分析' }}
            </h2>
            <p class="text-sm leading-7 text-[#b7bec7]">
                当前共 {{ total }} 条记录，正面 {{ positive }} 条，负面
                {{ negative }} 条。{{
                    negative
                        ? '建议优先处理负面内容并按高频话题分派负责人。'
                        : '当前未发现需要紧急处理的负面内容。'
                }}
            </p>
            <button class="deco-button primary mt-5" @click="analyzed = true">
                {{ analyzed ? '分析已生成' : '生成分析' }}
            </button>
            <div
                v-if="analyzed"
                class="mt-4 rounded-lg bg-[#2c3034] p-4 text-sm"
            >
                负面占比
                {{
                    Math.round((negative * 100) / Math.max(total, 1))
                }}%，建议按照总览中的高频话题和紧急事项处理。
            </div>
        </section>
    </div>
</template>
