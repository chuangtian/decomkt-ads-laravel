<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
type Task = {
    id: string;
    title: string;
    description?: string;
    status: string;
    priority: string;
    dueDate?: string;
    assignees: { id: number; name: string }[];
};
const props = defineProps<{
    tasks: Task[];
    employees: { id: number; name: string }[];
}>();
const visible = ref(false);
const form = useForm({
    title: '',
    description: '',
    priority: 'MEDIUM',
    dueDate: '',
    assigneeIds: [] as number[],
});
const columns = [
    { key: 'TODO', title: '待开始', color: '#4582e6' },
    { key: 'IN_PROGRESS', title: '进行中', color: '#ed7b2f' },
    { key: 'COMPLETED', title: '已完成', color: '#2ba471' },
    { key: 'OVERDUE', title: '逾期', color: '#e34d59' },
];
const tasksFor = (key: string) =>
    props.tasks.filter((t) =>
        key === 'OVERDUE'
            ? t.status !== 'COMPLETED' &&
              !!t.dueDate &&
              new Date(t.dueDate) < new Date()
            : t.status === key,
    );
const counts = computed(() => columns.map((c) => tasksFor(c.key).length));
const create = () =>
    form.post('/tasks', {
        onSuccess: () => {
            visible.value = false;
            form.reset();
        },
    });
const advance = (t: Task) =>
    router.put(`/tasks/${t.id}`, {
        status: t.status === 'TODO' ? 'IN_PROGRESS' : 'COMPLETED',
    });
const remove = (t: Task) => router.delete(`/tasks/${t.id}`);
</script>
<template>
    <Head title="任务看板" />
    <div class="deco-page">
        <div class="deco-header">
            <div>
                <h1 class="deco-title">任务看板</h1>
                <p class="deco-subtitle">团队任务管理与进度追踪</p>
            </div>
            <div class="deco-actions">
                <input
                    class="deco-input deco-date"
                    value="开始日期　-　结束日期"
                /><button class="deco-button primary" @click="visible = true">
                    + 新建任务
                </button>
            </div>
        </div>
        <div class="deco-metrics">
            <article
                v-for="(column, index) in columns"
                :key="column.key"
                class="deco-card deco-metric"
                :style="{ '--metric-color': column.color }"
            >
                <p class="deco-metric-label">{{ column.title }}</p>
                <p class="deco-metric-value">{{ counts[index] }}</p>
            </article>
        </div>
        <div class="grid gap-3 xl:grid-cols-4">
            <section
                v-for="column in columns"
                :key="column.key"
                class="min-h-72 rounded-lg bg-[#25292d] p-3"
            >
                <h2 class="mb-4 flex items-center gap-2 font-bold">
                    <i
                        class="size-2 rounded-full"
                        :style="{ background: column.color }"
                    ></i
                    >{{ column.title
                    }}<span
                        class="ml-auto text-sm"
                        :style="{ color: column.color }"
                        >{{ tasksFor(column.key).length }}</span
                    >
                </h2>
                <div v-if="!tasksFor(column.key).length" class="deco-empty">
                    暂无任务
                </div>
                <article
                    v-for="task in tasksFor(column.key)"
                    :key="task.id"
                    class="mb-3 rounded-lg border border-[#3b4148] bg-[#1c2024] p-3"
                >
                    <div class="flex justify-between gap-2">
                        <b class="text-sm">{{ task.title }}</b
                        ><span class="deco-pill">{{ task.priority }}</span>
                    </div>
                    <p class="mt-2 line-clamp-2 text-xs text-[#929aa4]">
                        {{ task.description || '暂无描述' }}
                    </p>
                    <p class="mt-3 text-xs">
                        {{
                            task.assignees.map((a) => a.name).join('、') ||
                            '未分配'
                        }}
                    </p>
                    <div class="mt-3 flex gap-2">
                        <button
                            v-if="task.status !== 'COMPLETED'"
                            class="deco-button"
                            @click="advance(task)"
                        >
                            {{
                                task.status === 'TODO' ? '开始' : '完成'
                            }}</button
                        ><button
                            class="deco-button text-red-400"
                            @click="remove(task)"
                        >
                            删除
                        </button>
                    </div>
                </article>
            </section>
        </div>
        <div
            v-if="visible"
            class="fixed inset-0 z-[80] grid place-items-center bg-black/65 p-4"
            @click.self="visible = false"
        >
            <form class="deco-card w-full max-w-lg" @submit.prevent="create">
                <h2 class="deco-card-title">新建任务</h2>
                <div class="grid gap-3">
                    <input
                        v-model="form.title"
                        class="deco-input"
                        placeholder="任务标题"
                        required
                    /><textarea
                        v-model="form.description"
                        class="deco-input min-h-24 py-2"
                        placeholder="任务描述"
                    ></textarea
                    ><select v-model="form.priority" class="deco-input">
                        <option value="LOW">低</option>
                        <option value="MEDIUM">中</option>
                        <option value="HIGH">高</option>
                        <option value="URGENT">紧急</option></select
                    ><input
                        v-model="form.dueDate"
                        type="datetime-local"
                        class="deco-input"
                    /><label class="text-sm">负责人</label>
                    <div class="grid grid-cols-2 gap-2">
                        <label
                            v-for="employee in employees"
                            :key="employee.id"
                            class="rounded bg-[#2b2e32] p-2 text-sm"
                            ><input
                                v-model="form.assigneeIds"
                                type="checkbox"
                                :value="employee.id"
                            />
                            {{ employee.name }}</label
                        >
                    </div>
                    <button class="deco-button primary">创建任务</button>
                </div>
            </form>
        </div>
    </div>
</template>
