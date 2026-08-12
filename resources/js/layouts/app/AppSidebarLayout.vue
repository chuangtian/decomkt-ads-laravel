<script setup lang="ts">
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import { Toaster } from '@/components/ui/sonner';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <div class="macfox-shell dark">
        <AppSidebarHeader :breadcrumbs="breadcrumbs" />
        <div class="macfox-body">
            <AppSidebar />
            <main class="macfox-content-scroll">
                <slot />
            </main>
        </div>
        <Toaster />
    </div>
</template>
