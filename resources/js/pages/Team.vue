<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
type Member = {
    id: number;
    name: string;
    jobTitle?: string;
    totalTasks: number;
    completedTasks: number;
    overdueTasks: number;
};
const props = defineProps<{ members: Member[] }>();
const total = computed(() =>
    props.members.reduce((s, m) => s + m.totalTasks, 0),
);
const completed = computed(() =>
    props.members.reduce((s, m) => s + m.completedTasks, 0),
);
</script>
<template>
    <Head title="团队成员" />
    <div class="deco-page">
        <div class="deco-header">
            <div>
                <h1 class="deco-title">团队成员</h1>
                <p class="deco-subtitle">成员状态、任务分配与协作概览</p>
            </div>
        </div>
        <div class="deco-metrics">
            <article
                v-for="metric in [
                    { l: '团队总人数', v: members.length },
                    { l: '总任务数', v: total },
                    { l: '已完成', v: completed },
                    {
                        l: '完成率',
                        v: total
                            ? Math.round((completed / total) * 100) + '%'
                            : '—',
                    },
                ]"
                :key="metric.l"
                class="deco-card deco-metric"
            >
                <p class="deco-metric-label">{{ metric.l }}</p>
                <p class="deco-metric-value">{{ metric.v }}</p>
            </article>
        </div>
        <div class="grid gap-4 md:grid-cols-2">
            <article
                v-for="member in members"
                :key="member.id"
                class="deco-card flex items-center gap-5"
            >
                <span
                    class="grid size-14 place-items-center rounded-full bg-emerald-500/10 text-xl text-emerald-400"
                    >{{ member.name.slice(0, 1) }}</span
                >
                <div class="flex-1">
                    <b>{{ member.name }}</b>
                    <p class="text-sm text-[#929aa4]">
                        {{ member.jobTitle || '未设置职位' }}
                    </p>
                </div>
                <div
                    v-for="metric in [
                        { l: '总任务', v: member.totalTasks },
                        { l: '已完成', v: member.completedTasks },
                        { l: '逾期', v: member.overdueTasks },
                    ]"
                    :key="metric.l"
                    class="text-center"
                >
                    <b>{{ metric.v }}</b>
                    <p class="text-xs text-[#929aa4]">{{ metric.l }}</p>
                </div>
            </article>
        </div>
        <section class="deco-card mt-4 min-h-48">
            <h2 class="deco-card-title">职位分布</h2>
            <div class="deco-empty">
                {{ members.length }} 人 · {{ total }} 任务
            </div>
        </section>
    </div>
</template>
