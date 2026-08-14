<script setup lang="ts">
import PaginationControls, { type PagePayload } from '@/components/PaginationControls.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type Row = Record<string, any>;
type Summary = { range:{start:string;end:string}; reviews:{total:number;satisfied:number;negative:number;average:number}; reddit:{total:number;views:number;comments:number;upvotes:number;negative:number} };
const props = defineProps<{ summary?:Summary|null; recordPage?:PagePayload<Row>|null }>();
const empty:Summary = { range:{start:'',end:''}, reviews:{total:0,satisfied:0,negative:0,average:0}, reddit:{total:0,views:0,comments:0,upvotes:0,negative:0} };
const data = computed(() => props.summary ?? empty);
const active = ref(0);
const start = ref(props.summary?.range.start ?? '');
const end = ref(props.summary?.range.end ?? '');
const loading = ref(false);
const loadedType = ref('');
const rows = computed(() => props.recordPage?.data ?? []);
const detailType = computed(() => active.value === 1 ? 'reviews' : active.value === 2 ? 'reddit' : '');
const tabs = computed(() => ['🎯 目标看板', `💬 评价管理（${data.value.reviews.total}）`, `🔴 Reddit（${data.value.reddit.total}）`, '🧵 Threads', '💡 AI 分析']);
const targets = computed(() => [
    { name:'满意评价目标', sub:'舆情评价表现', actual:data.value.reviews.satisfied },
    { name:'Reddit曝光目标', sub:'社区内容曝光表现', actual:data.value.reddit.views },
    { name:'Reddit评论目标', sub:'社区互动表现', actual:data.value.reddit.comments },
]);
const aiSummary = ref('');
const loadDetail = (type:string, page = 1, includeSummary = false) => {
    loading.value = true;
    loadedType.value = type;
    router.get('/reputation/overview', { detail:type || undefined, page, start:start.value, end:end.value }, { only:includeSummary ? ['reputationSummary', 'recordPage'] : ['recordPage'], preserveState:true, preserveScroll:true, replace:true, onFinish:() => loading.value=false });
};
const selectTab = (index:number) => { active.value=index; const type=index===1?'reviews':index===2?'reddit':''; if (type && (!props.recordPage || loadedType.value!==type)) loadDetail(type); };
const reloadRange = () => loadDetail(detailType.value, 1, true);
</script>

<template>
    <Head title="舆情总览" />
    <div class="deco-page">
        <div class="deco-header"><div><h1 class="deco-title">舆情总览</h1><p class="deco-subtitle">跨平台舆情监控与数据汇总 — Trustpilot · 官网评论 · Google 直评 · Reddit</p></div><div class="deco-actions"><input v-model="start" type="date" class="deco-input" @change="reloadRange"><span>-</span><input v-model="end" type="date" class="deco-input" @change="reloadRange"></div></div>
        <div class="deco-tabs"><button v-for="(tab,index) in tabs" :key="tab" class="deco-tab" :class="{active:active===index}" @click="selectTab(index)">{{tab}}</button></div>
        <template v-if="active===0"><h2 class="text-xl font-bold">舆情目标进度</h2><p class="mb-5 mt-1 text-sm text-[#9ba3ad]">{{start}} 至 {{end}}</p><div class="deco-grid-3"><article v-for="metric in targets" :key="metric.name" class="deco-card min-h-[350px] !border-t-2 !border-t-red-500"><div class="flex justify-between"><h2 class="deco-card-title">{{metric.name}}</h2><span class="deco-pill red">高风险</span></div><p class="text-sm text-[#aeb5bd]">{{metric.sub}}</p><p class="mt-7 text-4xl font-bold">{{metric.actual.toLocaleString()}}<small class="text-lg text-[#aeb5bd]"> / 0</small></p><p class="mt-4 text-sm">完成进度</p><p class="mt-2 text-2xl font-bold text-red-500">0.00%</p><div class="mt-3 h-3 rounded bg-[#383c41]"></div><div class="mt-7 grid grid-cols-3 border-t border-[#3c4147] pt-5 text-center text-xs"><span>实际值<b class="mt-2 block">{{metric.actual.toLocaleString()}}</b></span><span>目标值<b class="mt-2 block">0</b></span><span>超出目标<b class="mt-2 block">0</b></span></div></article></div><section class="deco-card"><h2 class="deco-card-title">舆情目标完成率排名</h2><div v-for="(metric,index) in targets" :key="metric.name" class="grid grid-cols-6 border-b border-[#3a3f45] py-4 text-sm"><span>🏅 {{index+1}}</span><b>{{metric.name}}</b><span>{{metric.actual}}</span><span>0</span><span class="text-red-400">0.00%</span><span>高风险</span></div></section></template>
        <template v-else-if="active===1"><div class="deco-metrics"><article v-for="metric in [{l:'期间评价',v:data.reviews.total},{l:'满意评价',v:data.reviews.satisfied},{l:'负面评价',v:data.reviews.negative},{l:'平均评分',v:data.reviews.average.toFixed(1)+'★'}]" :key="metric.l" class="deco-card deco-metric"><p class="deco-metric-label">{{metric.l}}</p><p class="deco-metric-value">{{metric.v}}</p></article></div><section class="deco-card"><div v-if="loading&&!recordPage" class="deco-empty">正在加载评价明细…</div><template v-else><article v-for="item in rows" :key="item.id" class="border-b border-[#383d43] py-4"><b class="text-amber-400">{{'★'.repeat(+item.star||0)}}</b><span class="ml-4 text-xs">{{item.platform}}</span><p class="mt-2 text-sm">{{item.content}}</p></article><PaginationControls :page="recordPage" :busy="loading" @change="page=>loadDetail('reviews',page)" /></template></section></template>
        <template v-else-if="active===2"><div class="deco-metrics"><article v-for="metric in [{l:'期间帖子',v:data.reddit.total},{l:'曝光',v:data.reddit.views.toLocaleString()},{l:'评论',v:data.reddit.comments},{l:'Upvotes',v:data.reddit.upvotes}]" :key="metric.l" class="deco-card deco-metric"><p class="deco-metric-label">{{metric.l}}</p><p class="deco-metric-value">{{metric.v}}</p></article></div><section class="deco-card"><div v-if="loading&&!recordPage" class="deco-empty">正在加载 Reddit 明细…</div><template v-else><article v-for="item in rows" :key="item.id" class="border-b border-[#383d43] py-4"><b>{{item.title}}</b><p class="mt-2 text-sm text-[#aeb5bd]">{{item.subreddit}}　⬆ {{item.upvotes}}　💬 {{item.commentCount}}</p></article><PaginationControls :page="recordPage" :busy="loading" @change="page=>loadDetail('reddit',page)" /></template></section></template>
        <section v-else-if="active===3" class="deco-card"><h2 class="deco-card-title">Threads 舆情</h2><div class="deco-empty big">当前尚未接入 Threads 数据源</div></section>
        <section v-else class="deco-card"><h2 class="deco-card-title">AI 舆情分析</h2><p class="text-sm leading-7 text-[#b7bec7]">当前区间共 {{data.reviews.total}} 条评价、{{data.reddit.total}} 条 Reddit 帖子。</p><button class="deco-button primary mt-5" @click="aiSummary=`当前满意评价 ${data.reviews.satisfied} 条、负面评价 ${data.reviews.negative} 条；Reddit 总曝光 ${data.reddit.views.toLocaleString()}。建议优先处理 1-2 星评价，并持续跟进 Reddit 负面帖子。`">生成 AI 分析</button><div v-if="aiSummary" class="mt-4 rounded-lg bg-[#2c3034] p-4 text-sm leading-7">{{aiSummary}}</div></section>
    </div>
</template>
