<script setup lang="ts">
import PaginationControls, { type PagePayload } from '@/components/PaginationControls.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref } from 'vue';

type Row = { id:string; number:string; brand:string; type:string; department:string; reporter:string; designer:string; priority:string; description:string; quantity:number; status:string; completed:boolean; delayed:boolean; delayDays:number; changes:number; score:number; submitDate:string|null; plannedDate:string|null; actualDate:string|null };
const props = defineProps<{summary:{total:number;inProgress:number;completed:number;delayed:number;quantity:number;averageScore:number};designers:Array<{name:string;tasks:number;quantity:number;completed:number}>;types:Array<{name:string;count:number}>;activeTasks:Row[];recordPage?:PagePayload<Row>|null;dataSync?:{recordCount:number;syncedAt:string|null;lastError:string|null}|null}>();
const tab = ref(0);
const syncing = ref(false);
const loading = ref(false);
const search = ref('');
const rows = computed(() => props.recordPage?.data ?? []);
let searchTimer: number | undefined;
const refresh = () => { syncing.value = true; router.post('/ecommerce/design/refresh', {}, { preserveScroll: true, onFinish: () => syncing.value = false }); };
const loadRecords = (page = 1) => {
    loading.value = true;
    router.get('/ecommerce/design', { detail: 'records', page, search: search.value || undefined }, { only: ['recordPage'], preserveState: true, preserveScroll: true, replace: true, onFinish: () => loading.value = false });
};
const selectTab = (value: number) => { tab.value = value; if (value === 1 && !props.recordPage) loadRecords(); };
const searchRecords = () => { window.clearTimeout(searchTimer); searchTimer = window.setTimeout(() => loadRecords(1), 350); };
const fmt = (date:string|null) => date ? date.slice(5).replace('-', '/') : '—';
onBeforeUnmount(() => window.clearTimeout(searchTimer));
</script>

<template>
    <Head title="视觉设计" />
    <div class="deco-page">
        <div class="deco-header"><div><h1 class="deco-title">视觉设计</h1><p class="deco-subtitle">设计需求管理与效率追踪</p></div><div class="deco-actions"><span v-if="dataSync?.syncedAt" class="text-xs text-[#929aa4]">最近同步：{{ new Date(dataSync.syncedAt).toLocaleString('zh-CN') }}</span><button class="deco-button primary" :disabled="syncing" @click="refresh">{{ syncing ? '同步中…' : '刷新' }}</button></div></div>
        <div class="deco-tabs"><button class="deco-tab" :class="{ active: tab === 0 }" @click="selectTab(0)">📊 效率总览</button><button class="deco-tab" :class="{ active: tab === 1 }" @click="selectTab(1)">📋 需求列表</button></div>
        <template v-if="tab === 0">
            <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6"><article v-for="item in [{l:'总需求',v:summary.total},{l:'进行中',v:summary.inProgress},{l:'已完成',v:summary.completed},{l:'延期数',v:summary.delayed},{l:'总数量',v:summary.quantity+' 件'},{l:'综合均分',v:summary.averageScore}]" :key="item.l" class="deco-card deco-metric"><p class="deco-metric-label">{{item.l}}</p><p class="deco-metric-value">{{item.v}}</p></article></div>
            <div class="deco-grid-2 mt-4">
                <section class="deco-card"><h2 class="deco-card-title">设计师工作量</h2><div v-for="designer in designers" :key="designer.name" class="mb-3 rounded bg-[#2d3034] p-3"><b>{{designer.name}}</b><div class="mt-2 flex gap-5 text-xs text-[#aab2bc]"><span>{{designer.tasks}} 任务</span><span>{{designer.quantity}} 件</span><span>{{designer.completed}} 完成</span></div></div></section>
                <section class="deco-card"><h2 class="deco-card-title">需求类型分布</h2><div v-for="item in types" :key="item.name" class="mb-3"><div class="mb-1 flex justify-between text-sm"><span>{{item.name}}</span><b>{{item.count}} 个</b></div><div class="h-2 overflow-hidden rounded bg-[#353a40]"><i class="block h-full rounded bg-blue-500" :style="{width:(item.count*100/Math.max(summary.total,1))+'%'}"></i></div></div></section>
            </div>
            <section class="deco-card mt-4"><h2 class="deco-card-title">⏳ 进行中任务（最多展示 12 个）</h2><div class="grid gap-3 lg:grid-cols-3"><article v-for="row in activeTasks" :key="row.id" class="rounded-lg border border-[#41464d] bg-[#292c30] p-4"><div class="flex items-center gap-2"><b class="text-blue-400">{{row.number}}</b><span class="deco-pill">{{row.type}}</span><span class="deco-pill red">{{row.priority}}</span></div><p class="my-3 line-clamp-2 min-h-10 text-sm">{{row.description||'—'}}</p><div class="flex justify-between text-xs text-[#9ba3ad]"><span>×{{row.quantity}} · {{row.reporter}}</span><span>计划 {{fmt(row.plannedDate)}}</span></div></article></div></section>
        </template>
        <template v-else>
            <div class="mb-3 flex justify-end"><input v-model="search" class="deco-input w-72" placeholder="搜索编号、类型、提报人…" @input="searchRecords"></div>
            <div v-if="loading && !recordPage" class="deco-card deco-empty">正在加载需求明细…</div>
            <template v-else><div class="deco-table-wrap"><table class="deco-table"><thead><tr><th>编号</th><th>需求类型</th><th>需求描述</th><th>提报人</th><th>设计师</th><th>数量</th><th>计划交付</th><th>状态</th><th>得分</th></tr></thead><tbody><tr v-for="row in rows" :key="row.id"><td class="font-mono text-blue-400">{{row.number}}</td><td>{{row.type}}</td><td class="max-w-md"><p class="line-clamp-2">{{row.description||'—'}}</p></td><td>{{row.reporter}}</td><td>{{row.designer}}</td><td>{{row.quantity}}</td><td>{{row.plannedDate||'—'}}</td><td><span class="deco-pill" :class="row.completed?'green':''">{{row.status||'—'}}</span></td><td>{{row.score||'—'}}</td></tr></tbody></table></div><PaginationControls :page="recordPage" :busy="loading" @change="loadRecords" /></template>
        </template>
    </div>
</template>
