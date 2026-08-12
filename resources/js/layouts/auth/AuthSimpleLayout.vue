<script setup lang="ts">
defineProps<{ title?: string; description?: string }>();
</script>
<template>
    <div class="min-h-screen bg-[linear-gradient(to_bottom,rgba(0,82,217,.12),#101418)] p-6 text-white grid place-items-center">
        <div class="w-full max-w-[420px] overflow-hidden rounded-[24px] border border-[#555b61] bg-[#242424] shadow-[0_22px_60px_rgba(0,0,0,.38)]">
            <div class="flex justify-center px-7 pt-8 pb-4"><img src="/logo.svg" alt="MACFOX" class="h-9 w-auto text-white"></div>
            <div class="border-b border-[#383d42] px-8 pb-6"><h1 class="text-2xl font-bold">{{ title }}</h1><p v-if="description" class="mt-2 text-sm text-[#929aa4]">{{ description }}</p></div>
            <div class="p-8"><slot /></div>
        </div>
    </div>
</template>
