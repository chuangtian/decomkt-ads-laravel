<script setup lang="ts">
import { computed } from 'vue';

export type PagePayload<T = unknown> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
};

const props = defineProps<{ page?: PagePayload | null; busy?: boolean }>();
const emit = defineEmits<{ change: [page: number] }>();

const visiblePages = computed(() => {
    const current = props.page?.current_page ?? 1;
    const last = props.page?.last_page ?? 1;
    const start = Math.max(1, Math.min(current - 2, last - 4));
    const end = Math.min(last, start + 4);

    return Array.from(
        { length: Math.max(0, end - start + 1) },
        (_, index) => start + index,
    );
});
</script>

<template>
    <div
        v-if="page && page.total"
        class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-[#383d43] pt-4 text-sm text-[#aeb5bd]"
    >
        <span>第 {{ page.from }}–{{ page.to }} 条，共 {{ page.total }} 条</span>
        <div class="flex items-center gap-2">
            <button
                class="deco-button"
                :disabled="busy || page.current_page <= 1"
                @click="emit('change', page.current_page - 1)"
            >
                上一页
            </button>
            <button
                v-for="number in visiblePages"
                :key="number"
                class="deco-button min-w-10"
                :class="{ primary: number === page.current_page }"
                :disabled="busy"
                @click="emit('change', number)"
            >
                {{ number }}
            </button>
            <button
                class="deco-button"
                :disabled="busy || page.current_page >= page.last_page"
                @click="emit('change', page.current_page + 1)"
            >
                下一页
            </button>
        </div>
    </div>
</template>
