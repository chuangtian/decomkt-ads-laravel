<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
type Row = Record<string, any>;
const props = defineProps<{
    analytics?: { rows?: Row[] };
    externalSync?: { syncedAt: string | null; lastError: string | null } | null;
}>();
const syncing = ref(false);
const selectedWeek = ref('全部');
const rows = computed(() => props.analytics?.rows || []);
const weeks = computed(() =>
    [...new Set(rows.value.map((r) => Number(r['周'])).filter(Boolean))].sort(
        (a, b) => b - a,
    ),
);
const currentWeek = computed(() =>
    selectedWeek.value === '全部'
        ? weeks.value[0] || 0
        : Number(selectedWeek.value),
);
const visible = computed(() =>
    rows.value.filter((r) => Number(r['周']) === currentWeek.value),
);
const sum = (key: string) =>
    visible.value.reduce((s, r) => s + (Number(r[key]) || 0), 0);
const avg = (key: string) =>
    visible.value.length ? sum(key) / visible.value.length : 0;
const revenue = computed(() => sum('营收'));
const openRate = computed(() => avg('打开率'));
const ctr = computed(() => avg('点击率'));
const cvr = computed(() => avg('转化率'));
const unsub = computed(() => avg('退订率'));
const money = (v: number) =>
    `$${v.toLocaleString('en-US', { minimumFractionDigits: v >= 1000 ? 0 : 2, maximumFractionDigits: v >= 1000 ? 0 : 2 })}`;
const pct = (v: number) => `${(v * 100).toFixed(1)}%`;
const refresh = () => {
    syncing.value = true;
    router.post(
        '/data-sync/refresh',
        { path: '/organic/edm' },
        { preserveScroll: true, onFinish: () => (syncing.value = false) },
    );
};
</script>
<template>
    <Head title="EDM 邮件营销" />
    <div class="deco-page">
        <div class="deco-header">
            <div>
                <h1 class="deco-title">EDM 邮件营销</h1>
                <p class="deco-subtitle">邮件列表健康度与自动化序列分析</p>
            </div>
            <div class="deco-actions">
                <span
                    class="text-xs text-[#929aa4]"
                    v-if="externalSync?.syncedAt"
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
        <div class="deco-tabs">
            <button class="deco-tab active">📊 总览</button
            ><button class="deco-tab">📧 序列表现</button
            ><button class="deco-tab">👥 用户分层</button
            ><button class="deco-tab">🎯 目标看板</button
            ><button class="deco-tab">💡 AI 分析</button>
        </div>
        <div class="deco-toolbar">
            <label class="text-sm text-[#aeb5bd]">周范围：</label
            ><select v-model="selectedWeek" class="deco-input">
                <option>全部</option>
                <option v-for="w in weeks" :key="w" :value="String(w)">
                    W{{ w }}
                </option></select
            ><label class="ml-4 text-sm text-[#aeb5bd]">对比：</label
            ><select class="deco-input">
                <option>无对比</option>
                <option>对比上一时间段</option>
            </select>
        </div>
        <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
            <article
                v-for="m in [
                    {
                        l: `Email Revenue · Week ${currentWeek}`,
                        v: money(revenue),
                    },
                    { l: 'Open Rate', v: pct(openRate) },
                    { l: 'CTR', v: pct(ctr) },
                    { l: 'CVR', v: pct(cvr) },
                    { l: 'Subscribers', v: '—' },
                    { l: 'Unsubscribe Rate', v: pct(unsub) },
                ]"
                :key="m.l"
                class="deco-card deco-metric"
            >
                <p class="deco-metric-label">{{ m.l }}</p>
                <p class="deco-metric-value">{{ m.v }}</p>
            </article>
        </div>
        <div class="deco-grid-2 mt-4">
            <section class="deco-card min-h-[330px]">
                <h2 class="deco-card-title">Email Revenue Trend</h2>
                <div class="flex h-56 items-end justify-center gap-4">
                    <div
                        v-for="r in visible"
                        :key="r['序列名称']"
                        class="w-10 rounded-t bg-blue-500"
                        :style="{
                            height:
                                Math.max(
                                    3,
                                    ((Number(r['营收']) || 0) * 190) /
                                        Math.max(
                                            ...visible.map(
                                                (x) => Number(x['营收']) || 0,
                                            ),
                                            1,
                                        ),
                                ) + 'px',
                        }"
                        :title="`${r['序列名称']}: ${money(Number(r['营收']) || 0)}`"
                    ></div>
                </div>
            </section>
            <section class="deco-card">
                <h2 class="deco-card-title">
                    Top Revenue Flows · Week {{ currentWeek }}
                </h2>
                <div
                    v-for="r in [...visible]
                        .sort((a, b) => (b['营收'] || 0) - (a['营收'] || 0))
                        .slice(0, 6)"
                    :key="r['序列名称']"
                    class="flex justify-between border-b border-[#383d43] py-3"
                >
                    <span>{{ r['序列名称'] }}</span
                    ><b class="text-blue-400">{{
                        money(Number(r['营收']) || 0)
                    }}</b>
                </div>
            </section>
        </div>
        <section class="deco-card">
            <h2 class="deco-card-title">
                Flow Performance · Week {{ currentWeek }}
            </h2>
            <div class="deco-table-wrap border-0">
                <table class="deco-table">
                    <thead>
                        <tr>
                            <th>Flow</th>
                            <th>Revenue</th>
                            <th>Open Rate</th>
                            <th>CTR</th>
                            <th>Conversion</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="r in visible" :key="r.record_id">
                            <td>{{ r['序列名称'] }}</td>
                            <td>{{ money(Number(r['营收']) || 0) }}</td>
                            <td>{{ pct(Number(r['打开率']) || 0) }}</td>
                            <td>{{ pct(Number(r['点击率']) || 0) }}</td>
                            <td>{{ pct(Number(r['转化率']) || 0) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
