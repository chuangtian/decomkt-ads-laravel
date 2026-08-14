<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { Check, ChevronDown, LogOut, Settings, Store, UserRound } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

type StoreRecord = { id: string; name: string; slug: string; logoUrl?: string | null; timezone: string };

const page = usePage();
const open = ref(false);
const root = ref<HTMLElement | null>(null);
const stores = computed(() => (page.props.stores as StoreRecord[] | undefined) ?? []);
const currentStore = computed(() => page.props.currentStore as StoreRecord | undefined);
const user = computed(() => page.props.auth.user as { name?: string; email?: string });
const canManageStores = computed(() => {
    const paths = (page.props.auth as { allowedPaths?: string[] }).allowedPaths ?? [];
    return paths.includes('*') || paths.includes('/stores');
});
const initials = (name?: string) => (name || 'S').trim().slice(0, 2).toUpperCase();
const switchStore = (store: StoreRecord) => {
    if (store.id === currentStore.value?.id) {
        open.value = false;
        return;
    }
    router.post('/stores/switch', { storeId: store.id, returnTo: page.url.split('?')[0] === '/stores' ? '/' : page.url }, {
        preserveScroll: false,
        preserveState: false,
        onStart: () => { open.value = false; },
    });
};
const handleOutside = (event: MouseEvent) => {
    if (root.value && !root.value.contains(event.target as Node)) open.value = false;
};
onMounted(() => document.addEventListener('click', handleOutside));
onBeforeUnmount(() => document.removeEventListener('click', handleOutside));
</script>

<template>
    <div ref="root" class="store-switcher">
        <button class="store-switcher-trigger" :aria-expanded="open" @click="open = !open">
            <span class="store-avatar">{{ initials(currentStore?.name) }}</span>
            <span class="store-trigger-copy"><b>{{ currentStore?.name || '选择店铺' }}</b><small>{{ currentStore?.slug || '未选择' }}</small></span>
            <ChevronDown class="size-4" />
        </button>

        <div v-if="open" class="store-switcher-panel">
            <div class="store-switcher-heading"><span>我的店铺</span><Store class="size-4" /></div>
            <button v-for="store in stores" :key="store.id" class="store-switcher-row" :class="{ current: store.id === currentStore?.id }" @click="switchStore(store)">
                <span class="store-avatar small">{{ initials(store.name) }}</span>
                <span class="store-row-copy"><b>{{ store.name }}</b><small>{{ store.slug }}</small></span>
                <Check v-if="store.id === currentStore?.id" class="ml-auto size-4" />
            </button>
            <Link v-if="canManageStores" href="/stores" class="store-switcher-action" @click="open = false"><Settings class="size-4" />管理店铺与模块</Link>
            <div class="store-switcher-divider" />
            <Link href="/account/profile" class="store-account" @click="open = false">
                <span class="store-avatar account"><UserRound class="size-4" /></span>
                <span class="store-row-copy"><b>{{ user?.name || '系统管理员' }}</b><small>{{ user?.email }}</small></span>
            </Link>
            <button class="store-switcher-action danger" @click="router.post('/logout')"><LogOut class="size-4" />退出登录</button>
        </div>
    </div>
</template>
