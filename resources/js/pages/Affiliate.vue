<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type RawRow = Record<string, unknown>;
const props = defineProps<{ analytics?: { rows?: RawRow[] }; externalSync?: { syncedAt: string | null; lastError: string | null } | null }>();
const today = new Date();
const end = ref(today.toISOString().slice(0, 10));
const startDate = new Date(today); startDate.setDate(today.getDate() - 7);
const start = ref(startDate.toISOString().slice(0, 10));
const affiliate = ref('全部联盟');
const syncing = ref(false);

const numberValue = (value: unknown) => Number(String(value ?? 0).replace(/[$,%]/g, '')) || 0;
const dateValue = (value: unknown) => {
  const timestamp = Number(value);
  if (!timestamp) return '';
  return new Date(timestamp > 9999999999 ? timestamp : timestamp * 1000).toISOString().slice(0, 10);
};
const rawRows = computed(() => props.analytics?.rows || []);
const affiliates = computed(() => ['全部联盟', ...new Set(rawRows.value.flatMap(row => Array.isArray(row.Name) ? row.Name.map(String) : [String(row.Name || '')]).filter(Boolean))]);
const rows = computed(() => rawRows.value.map(row => {
  const weekStart = dateValue(row['开始日期']);
  const weekNo = Array.isArray(row['周']) ? String(row['周'][0] || '') : String(row['周'] || '');
  const name = Array.isArray(row.Name) ? String(row.Name[0] || '') : String(row.Name || '');
  return { week: `W${weekNo.padStart(2, '0')}${weekStart ? ` (${weekStart.slice(5).replace('-', '/')})` : ''}`, weekStart, clicks: numberValue(row['点击']), gmv: numberValue(row.GMV), affiliate: name };
}).filter(row => row.weekStart >= start.value && row.weekStart <= end.value && (affiliate.value === '全部联盟' || row.affiliate === affiliate.value)));
const weekly = computed(() => {
  const grouped = new Map<string, {week:string;weekStart:string;clicks:number;gmv:number}>();
  rows.value.forEach(row => { const old = grouped.get(row.weekStart); if (old) { old.clicks += row.clicks; old.gmv += row.gmv; } else grouped.set(row.weekStart, {...row}); });
  return [...grouped.values()].sort((a,b) => a.weekStart.localeCompare(b.weekStart));
});
const totalGmv = computed(() => weekly.value.reduce((sum,row) => sum + row.gmv, 0));
const totalClicks = computed(() => weekly.value.reduce((sum,row) => sum + row.clicks, 0));
const average = computed(() => totalClicks.value ? totalGmv.value / totalClicks.value : 0);
const money = (value:number) => `$${value.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2})}`;
const points = (key:'gmv'|'clicks', width=620, height=220) => {
  if (!weekly.value.length) return '';
  const max = Math.max(...weekly.value.map(row => row[key]), 1);
  return weekly.value.map((row,index) => `${weekly.value.length === 1 ? width/2 : 35 + index*(width-70)/(weekly.value.length-1)},${height-25-row[key]*(height-55)/max}`).join(' ');
};
const refresh = () => { syncing.value=true; router.post('/data-sync/refresh',{path:'/organic/affiliate'},{preserveScroll:true,onFinish:()=>syncing.value=false}); };
</script>

<template>
  <Head title="联盟营销看板" />
  <div class="deco-page">
    <div class="deco-header">
      <div><h1 class="deco-title">联盟营销看板</h1><p class="deco-subtitle">Affiliate Marketing · 联盟合作伙伴数据看板</p></div>
      <div class="deco-actions">
        <input v-model="start" type="date" class="deco-input"><span class="text-[#9aa2ac]">-</span><input v-model="end" type="date" class="deco-input">
        <select v-model="affiliate" class="deco-input min-w-40"><option v-for="name in affiliates" :key="name">{{name}}</option></select>
        <button class="deco-button primary" :disabled="syncing" @click="refresh">{{syncing?'同步中…':'刷新'}}</button>
      </div>
    </div>
    <p v-if="externalSync?.lastError" class="deco-alert">{{externalSync.lastError}}</p>
    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
      <article class="deco-card deco-metric"><p class="deco-metric-label">🪙　总 GMV</p><p class="deco-metric-value !text-amber-500">{{money(totalGmv)}}</p><p class="deco-metric-detail">较上周期 $0.00</p></article>
      <article class="deco-card deco-metric"><p class="deco-metric-label">👆　总点击数</p><p class="deco-metric-value !text-violet-500">{{totalClicks.toLocaleString()}}</p><p class="deco-metric-detail">较上周期 0</p></article>
      <article class="deco-card deco-metric"><p class="deco-metric-label">📊　平均 GMV / Click</p><p class="deco-metric-value">{{money(average)}}</p><p class="deco-metric-detail">较上周期 $0.00</p></article>
    </div>
    <div class="deco-grid-2 mt-5">
      <section class="deco-card min-h-[410px]"><h2 class="deco-card-title">GMV 趋势（按周）</h2><div v-if="!weekly.length" class="deco-empty big">暂无数据</div><svg v-else viewBox="0 0 620 220" class="mt-12 w-full"><line x1="35" y1="195" x2="585" y2="195" stroke="#42474e"/><polyline :points="points('gmv')" fill="none" stroke="#2ba471" stroke-width="3"/><circle v-for="(row,i) in weekly" :key="row.week" :cx="weekly.length===1?310:35+i*550/(weekly.length-1)" :cy="195-row.gmv*165/Math.max(...weekly.map(r=>r.gmv),1)" r="4" fill="#2ba471"/></svg></section>
      <section class="deco-card min-h-[410px]"><h2 class="deco-card-title">Clicks 与 GMV 趋势（按周）</h2><div v-if="!weekly.length" class="deco-empty big">暂无数据</div><svg v-else viewBox="0 0 620 220" class="mt-12 w-full"><line x1="35" y1="195" x2="585" y2="195" stroke="#42474e"/><polyline :points="points('clicks')" fill="none" stroke="#2ba471" stroke-width="3"/><polyline :points="points('gmv')" fill="none" stroke="#7657f6" stroke-width="3"/></svg></section>
    </div>
    <section class="deco-card"><h2 class="deco-card-title">每周关键指标</h2><div class="deco-table-wrap border-0"><table class="deco-table"><thead><tr><th>周</th><th>点击数</th><th>GMV (USD)</th><th>GMV / Click</th></tr></thead><tbody><tr v-for="row in weekly" :key="row.weekStart"><td>{{row.week}}</td><td>{{row.clicks.toLocaleString()}}</td><td>{{money(row.gmv)}}</td><td>{{money(row.clicks?row.gmv/row.clicks:0)}}</td></tr><tr v-if="!weekly.length"><td colspan="4" class="py-12 text-center text-[#929aa4]">暂无数据</td></tr></tbody></table></div><p class="mt-4 text-xs text-[#929aa4]">注：数据基于开始日期→结束日期；GMV / Click = GMV ÷ Clicks</p></section>
  </div>
</template>
