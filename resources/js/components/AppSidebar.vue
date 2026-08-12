<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    BadgeAlert, BarChart3, Bot, BriefcaseBusiness, ChevronDown, CircleGauge,
    ClipboardList, Image, LayoutGrid, LockKeyhole, Mail, Megaphone, MessageSquare,
    Search, Settings, ShoppingBag, ShoppingCart, Star, Store, Target, Users,
} from '@lucide/vue';
import { computed, ref } from 'vue';

type MenuItem = { title: string; href: string; icon?: object };
type MenuGroup = { title: string; icon: object; items?: MenuItem[]; href?: string };

const groups: MenuGroup[] = [
    { title: '工作台', icon: LayoutGrid, items: [
        { title: '总览仪表盘', href: '/', icon: CircleGauge },
        { title: '品牌资料', href: '/workspace/brand', icon: Image },
        { title: '亚马逊', href: '/ecommerce/amazon', icon: ShoppingBag },
        { title: 'Shopify', href: '/ecommerce/shopify', icon: Store },
        { title: '学生折扣', href: '/ecommerce/student-discounts', icon: Star },
        { title: '活动主题', href: '/ads/campaign', icon: Megaphone },
        { title: 'AI 营销大脑', href: '/workspace/ai-brain', icon: Bot },
    ]},
    { title: '付费广告', icon: Megaphone, items: [
        { title: '广告目标', href: '/ads/target', icon: Target },
        { title: 'Facebook Ads', href: '/ads/facebook' },
        { title: 'Google Ads', href: '/ads/google' },
        { title: 'TikTok Ads', href: '/ads/tiktok' },
        { title: 'Bing Ads', href: '/ads/bing', icon: Search },
        { title: 'Criteo', href: '/ads/criteo', icon: CircleGauge },
    ]},
    { title: '自然流量', icon: BarChart3, items: [
        { title: 'SEO / GEO', href: '/organic/seo' },
        { title: '品牌官媒', href: '/organic/social', icon: MessageSquare },
        { title: '红人运营', href: '/organic/kol', icon: Users },
        { title: 'EDM 邮件', href: '/organic/edm', icon: Mail },
        { title: '联盟营销', href: '/organic/affiliate', icon: Star },
    ]},
    { title: '中台', icon: ShoppingCart, items: [
        { title: '视觉设计', href: '/ecommerce/design', icon: Image },
    ]},
    { title: '舆情监控', icon: MessageSquare, items: [
        { title: '舆情总览', href: '/reputation/overview', icon: BarChart3 },
        { title: '风险同步', href: '/reputation/risk-sync', icon: BadgeAlert },
    ]},
    { title: '协作', icon: ClipboardList, items: [
        { title: '任务看板', href: '/collab/kanban', icon: ClipboardList },
        { title: '团队成员', href: '/collab/team', icon: Users },
    ]},
    { title: '人员管理', icon: Users, items: [
        { title: '人员管理', href: '/employees', icon: Users },
        { title: '角色管理', href: '/roles', icon: LockKeyhole },
    ]},
    { title: '插件管理', icon: LayoutGrid, items: [
        { title: '插件中心', href: '/plugins', icon: LayoutGrid },
    ]},
    { title: '店铺设置', icon: ShoppingBag, href: '/store-settings' },
    { title: '系统设置', icon: Settings, items: [
        { title: '系统设置', href: '/settings', icon: Settings },
        { title: '店铺管理', href: '/stores', icon: Store },
    ]},
];

const page = usePage();
const currentPath = computed(() => page.url.split('?')[0]);
const allowedPaths = computed(() => (page.props.auth as { allowedPaths?: string[] }).allowedPaths || []);
const canAccess = (href: string) => allowedPaths.value.includes('*') || allowedPaths.value.includes(href);
const visibleGroups = computed(() => groups.map((group) => ({ ...group, items: group.items?.filter((item) => canAccess(item.href)) })).filter((group) => group.href ? canAccess(group.href) : Boolean(group.items?.length)));
const isActive = (href: string) => currentPath.value === href;
const groupContainsActive = (group: MenuGroup) => group.href === currentPath.value || group.items?.some((item) => isActive(item.href));
const expanded = ref<Record<string, boolean>>(Object.fromEntries(groups.map((group) => [group.title, Boolean(groupContainsActive(group))])));
</script>

<template>
    <aside class="macfox-sidebar">
        <nav class="macfox-nav" aria-label="主导航">
            <section v-for="group in visibleGroups" :key="group.title" class="macfox-nav-group">
                <Link
                    v-if="group.href"
                    :href="group.href"
                    class="macfox-nav-parent"
                    :class="{ active: isActive(group.href) }"
                >
                    <component :is="group.icon" class="size-[18px]" />
                    <span>{{ group.title }}</span>
                </Link>
                <template v-else>
                    <button class="macfox-nav-parent" :class="{ active: groupContainsActive(group) }" @click="expanded[group.title] = !expanded[group.title]">
                        <component :is="group.icon" class="size-[18px]" />
                        <span>{{ group.title }}</span>
                        <ChevronDown class="ml-auto size-4 transition-transform" :class="{ 'rotate-180': expanded[group.title] }" />
                    </button>
                    <div v-show="expanded[group.title]" class="macfox-nav-children">
                        <Link v-for="item in group.items" :key="item.href" :href="item.href" class="macfox-nav-item" :class="{ active: isActive(item.href) }">
                            <component :is="item.icon || BriefcaseBusiness" class="size-[18px]" />
                            <span>{{ item.title }}</span>
                        </Link>
                    </div>
                </template>
            </section>
        </nav>
        <Link href="/account/profile" class="macfox-sidebar-avatar" title="账户设置">A</Link>
    </aside>
</template>
