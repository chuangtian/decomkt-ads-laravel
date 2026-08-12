<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Bell, Moon, Plus, Store, UserRound } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import type { BreadcrumbItem } from '@/types';

withDefaults(defineProps<{ breadcrumbs?: BreadcrumbItem[] }>(), { breadcrumbs: () => [] });
const page = usePage();
const user = computed(() => page.props.auth.user as { name: string; email: string });
const clock = ref('');
let timer: ReturnType<typeof setInterval>;
const tick = () => {
    clock.value = new Intl.DateTimeFormat('zh-CN', {
        timeZone: 'America/Los_Angeles', year: 'numeric', month: '2-digit', day: '2-digit',
        weekday: 'short', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false,
    }).format(new Date()).replaceAll('/', '-');
};
onMounted(() => {
    tick(); timer = setInterval(tick, 1000);
});
onBeforeUnmount(() => clearInterval(timer));
</script>

<template>
    <header class="macfox-topbar">
        <Link href="/" class="macfox-brand" aria-label="Macfox 首页">
            <img src="/logo.svg" alt="MACFOX" />
        </Link>
        <div class="macfox-topbar-actions">
            <span class="macfox-clock"><small>US</small>{{ clock }} <small>PT</small></span>
            <button class="macfox-icon-button" aria-label="新建"><Plus class="size-[18px]" /></button>
            <button class="macfox-icon-button" aria-label="通知"><Bell class="size-[18px]" /></button>
            <button class="macfox-icon-button" aria-label="主题"><Moon class="size-[18px]" /></button>
            <Link href="/stores" class="macfox-topbar-link"><Store class="size-4" />默认店铺</Link>
            <Link href="/account/profile" class="macfox-topbar-link"><UserRound class="size-4" />{{ user?.name || '系统管理员' }}</Link>
        </div>
    </header>
</template>
