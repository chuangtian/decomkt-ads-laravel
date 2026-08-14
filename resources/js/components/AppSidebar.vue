<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    BadgeAlert, BarChart3, Blocks, Bot, BriefcaseBusiness, ChevronDown, CircleGauge,
    ClipboardList, Image, LayoutGrid, LockKeyhole, Mail, Megaphone, MessageSquare,
    Search, Settings, ShoppingBag, ShoppingCart, Star, Store, Target, Users,
} from '@lucide/vue';
import type { Component } from 'vue';
import { computed, onMounted, ref, watch } from 'vue';

type MenuItem = { title: string; path: string; href: string; icon: string };
type MenuGroup = { title: string; icon: string; items: MenuItem[] };

const iconMap: Record<string, Component> = {
    'badge-alert': BadgeAlert, 'bar-chart': BarChart3, blocks: Blocks, bot: Bot,
    briefcase: BriefcaseBusiness, 'circle-gauge': CircleGauge, 'clipboard-list': ClipboardList,
    image: Image, 'layout-grid': LayoutGrid, lock: LockKeyhole, mail: Mail,
    megaphone: Megaphone, 'message-square': MessageSquare, search: Search, settings: Settings,
    'shopping-bag': ShoppingBag, 'shopping-cart': ShoppingCart, star: Star, store: Store,
    target: Target, users: Users,
};

const page = usePage();
const currentPath = computed(() => page.url.split('?')[0]);
const storeGroups = computed(() => (page.props.storeNavigation as MenuGroup[] | undefined) ?? []);
const globalGroups = computed(() => (page.props.globalNavigation as MenuGroup[] | undefined) ?? []);
const allGroups = computed(() => [...storeGroups.value, ...globalGroups.value]);
const isActive = (href: string) => currentPath.value === href;
const groupContainsActive = (group: MenuGroup) => group.items.some((item) => isActive(item.href));
const activeGroupTitle = () => allGroups.value.find(groupContainsActive)?.title ?? null;
const expandedGroup = ref<string | null>(activeGroupTitle());
const toggleGroup = (title: string) => {
    expandedGroup.value = expandedGroup.value === title ? null : title;
};
watch(currentPath, () => {
    expandedGroup.value = activeGroupTitle();
});
onMounted(() => void import('@/pages/Transition.vue'));
</script>

<template>
    <aside class="macfox-sidebar">
        <nav class="macfox-nav" aria-label="主导航">
            <p class="macfox-nav-section-label">当前店铺</p>
            <section v-for="group in storeGroups" :key="group.title" class="macfox-nav-group">
                <button class="macfox-nav-parent" :class="{ active: groupContainsActive(group) }" @click="toggleGroup(group.title)">
                    <component :is="iconMap[group.icon] || LayoutGrid" class="size-[18px]" />
                    <span>{{ group.title }}</span>
                    <ChevronDown class="ml-auto size-4 transition-transform" :class="{ 'rotate-180': expandedGroup === group.title }" />
                </button>
                <div v-show="expandedGroup === group.title" class="macfox-nav-children">
                    <Link v-for="item in group.items" :key="item.path" :href="item.href" prefetch="hover" cache-for="1m" instant component="Transition" class="macfox-nav-item" :class="{ active: isActive(item.href) }">
                        <component :is="iconMap[item.icon] || BriefcaseBusiness" class="size-[18px]" />
                        <span>{{ item.title }}</span>
                    </Link>
                    <p v-if="group.items.length === 0" class="macfox-nav-empty">本店暂未启用子页面</p>
                </div>
            </section>

            <div v-if="globalGroups.length" class="macfox-nav-divider" />
            <p v-if="globalGroups.length" class="macfox-nav-section-label">组织与系统</p>
            <section v-for="group in globalGroups" :key="group.title" class="macfox-nav-group">
                <button class="macfox-nav-parent" :class="{ active: groupContainsActive(group) }" @click="toggleGroup(group.title)">
                    <component :is="iconMap[group.icon] || Settings" class="size-[18px]" />
                    <span>{{ group.title }}</span>
                    <ChevronDown class="ml-auto size-4 transition-transform" :class="{ 'rotate-180': expandedGroup === group.title }" />
                </button>
                <div v-show="expandedGroup === group.title" class="macfox-nav-children">
                    <Link v-for="item in group.items" :key="item.path" :href="item.href" prefetch="hover" cache-for="1m" instant component="Transition" class="macfox-nav-item" :class="{ active: isActive(item.href) }">
                        <component :is="iconMap[item.icon] || BriefcaseBusiness" class="size-[18px]" />
                        <span>{{ item.title }}</span>
                    </Link>
                </div>
            </section>
        </nav>
        <Link href="/account/profile" prefetch="hover" cache-for="1m" instant component="Transition" class="macfox-sidebar-avatar" title="账户设置">A</Link>
    </aside>
</template>
