<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Blocks, Check, KeyRound, Settings, Store as StoreIcon, Users } from '@lucide/vue';
import { computed, ref } from 'vue';

type StoreRecord = {
    id: string; slug: string; name: string; timezone: string; status: string;
    memberCount: number; memberIds: number[]; memberRoleIds: Record<string, string | null>; moduleCount: number; credentialCount: number; enabledPaths: string[];
};
type Employee = { id: number; name: string; jobTitle?: string; isDefaultAdmin: boolean };
type Role = { id: string; name: string };
type ModuleItem = { title: string; path: string; icon: string };
type ModuleGroup = { title: string; icon: string; items: ModuleItem[] };

const props = defineProps<{ stores: StoreRecord[]; employees: Employee[]; roles: Role[]; moduleGroups: ModuleGroup[]; currentStoreId: string }>();
const visible = ref(false);
const editing = ref<StoreRecord | null>(null);
const memberStore = ref<StoreRecord | null>(null);
const moduleStore = ref<StoreRecord | null>(null);
const form = useForm({ name: '', slug: '', timezone: 'America/Los_Angeles', status: 'ACTIVE' });
const memberForm = useForm({ employeeIds: [] as number[], roleIds: {} as Record<number, string> });
const moduleForm = useForm({ paths: [] as string[] });
const totalModules = computed(() => props.moduleGroups.reduce((sum, group) => sum + group.items.length, 0));

const open = (store?: StoreRecord) => {
    editing.value = store || null;
    form.name = store?.name || '';
    form.slug = store?.slug || '';
    form.timezone = store?.timezone || 'America/Los_Angeles';
    form.status = store?.status || 'ACTIVE';
    visible.value = true;
};
const save = () => editing.value
    ? form.put(`/stores/${editing.value.id}`, { onSuccess: () => { visible.value = false; editing.value = null; } })
    : form.post('/stores', { onSuccess: () => { visible.value = false; form.reset(); } });
const remove = (store: StoreRecord) => {
    if (confirm(`确认删除店铺 ${store.name}？该操作只允许空店铺执行。`)) router.delete(`/stores/${store.id}`);
};
const openMembers = (store: StoreRecord) => {
    memberStore.value = store;
    memberForm.employeeIds = [...store.memberIds];
    memberForm.roleIds = Object.fromEntries(Object.entries(store.memberRoleIds || {}).filter(([, value]) => value).map(([key, value]) => [Number(key), value as string]));
};
const saveMembers = () => memberStore.value && memberForm.put(`/stores/${memberStore.value.id}/members`, { onSuccess: () => { memberStore.value = null; memberForm.reset(); } });
const openModules = (store: StoreRecord) => {
    moduleStore.value = store;
    moduleForm.paths = [...store.enabledPaths];
};
const saveModules = () => moduleStore.value && moduleForm.put(`/stores/${moduleStore.value.id}/modules`, { onSuccess: () => { moduleStore.value = null; moduleForm.reset(); } });
const selectGroup = (group: ModuleGroup, enabled: boolean) => {
    const paths = new Set(moduleForm.paths);
    group.items.forEach((item) => enabled ? paths.add(item.path) : paths.delete(item.path));
    moduleForm.paths = [...paths];
};
const switchStore = (store: StoreRecord) => router.post('/stores/switch', { storeId: store.id, returnTo: '/' }, { preserveState: false });
</script>

<template>
    <Head title="多店铺管理" />
    <div class="deco-page">
        <div class="deco-header">
            <div><h1 class="deco-title">多店铺管理</h1><p class="deco-subtitle">店铺数据、平台凭证、成员与功能模块彼此隔离。先切换店铺，再进入该店业务页面。</p></div>
            <button class="deco-button primary" @click="open()">新建店铺</button>
        </div>

        <section class="grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
            <article v-for="store in props.stores" :key="store.id" class="deco-card relative overflow-hidden">
                <div v-if="store.id === currentStoreId" class="absolute right-0 top-0 flex items-center gap-1 rounded-bl-lg bg-[#254c82] px-3 py-1.5 text-xs text-[#83b3ff]"><Check class="size-3" />当前店铺</div>
                <div class="flex items-start gap-3">
                    <span class="grid size-11 shrink-0 place-items-center rounded-lg bg-[#1c3e6e] text-[#6ca4f6]"><StoreIcon class="size-5" /></span>
                    <div class="min-w-0 flex-1"><div class="flex items-center gap-2"><h2 class="truncate text-base font-bold">{{ store.name }}</h2><span class="deco-pill" :class="store.status === 'ACTIVE' ? 'green' : 'red'">{{ store.status === 'ACTIVE' ? '启用' : '停用' }}</span></div><p class="mt-1 truncate font-mono text-xs text-[#929aa4]">{{ store.slug }} · {{ store.timezone }}</p></div>
                </div>
                <div class="my-5 grid grid-cols-3 gap-2">
                    <div class="rounded-md bg-[#171a1e] p-3"><Users class="mb-2 size-4 text-[#6ca4f6]" /><b class="block text-lg">{{ store.memberCount }}</b><small class="text-[#858e99]">成员</small></div>
                    <div class="rounded-md bg-[#171a1e] p-3"><Blocks class="mb-2 size-4 text-[#43c88d]" /><b class="block text-lg">{{ store.moduleCount }}</b><small class="text-[#858e99]">模块</small></div>
                    <div class="rounded-md bg-[#171a1e] p-3"><KeyRound class="mb-2 size-4 text-[#c38cf1]" /><b class="block text-lg">{{ store.credentialCount }}</b><small class="text-[#858e99]">配置项</small></div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button v-if="store.id !== currentStoreId && store.status === 'ACTIVE'" class="deco-button primary" @click="switchStore(store)">进入店铺</button>
                    <button class="deco-button" @click="openModules(store)"><Blocks class="size-4" />功能模块</button>
                    <button class="deco-button" @click="openMembers(store)"><Users class="size-4" />成员</button>
                    <button class="deco-button" @click="open(store)"><Settings class="size-4" />设置</button>
                    <button class="deco-button text-red-400" :disabled="store.id === 'default-store'" @click="remove(store)">删除</button>
                </div>
            </article>
        </section>

        <div v-if="visible" class="fixed inset-0 z-[100] grid place-items-center bg-black/65 p-4" @click.self="visible = false"><form class="deco-card w-full max-w-lg" @submit.prevent="save"><div class="flex items-center justify-between"><h2 class="deco-card-title">{{ editing ? '编辑店铺' : '新建店铺' }}</h2><button type="button" @click="visible = false">×</button></div><div class="grid gap-3"><label class="grid gap-1 text-xs text-[#929aa4]">店铺名称<input v-model="form.name" class="deco-input" required></label><label class="grid gap-1 text-xs text-[#929aa4]">唯一标识<input v-model="form.slug" class="deco-input" placeholder="例如 macfox-bike" :disabled="!!editing"></label><label class="grid gap-1 text-xs text-[#929aa4]">业务时区<input v-model="form.timezone" class="deco-input" required></label><label v-if="editing" class="grid gap-1 text-xs text-[#929aa4]">状态<select v-model="form.status" class="deco-input"><option value="ACTIVE">启用</option><option value="DISABLED">停用</option></select></label><p v-if="Object.keys(form.errors).length" class="text-sm text-red-400">{{ Object.values(form.errors)[0] }}</p><button class="deco-button primary" :disabled="form.processing">保存店铺</button></div></form></div>

        <div v-if="memberStore" class="fixed inset-0 z-[100] grid place-items-center bg-black/65 p-4" @click.self="memberStore = null"><form class="deco-card max-h-[85vh] w-full max-w-2xl overflow-auto" @submit.prevent="saveMembers"><div class="flex items-center justify-between"><div><h2 class="deco-card-title !mb-1">{{ memberStore.name }} · 成员与店铺角色</h2><p class="text-xs text-[#929aa4]">每名成员可在不同店铺拥有不同角色；超级管理员始终拥有全部权限。</p></div><button type="button" @click="memberStore = null">×</button></div><div class="mt-5 grid gap-2"><div v-for="employee in employees" :key="employee.id" class="grid items-center gap-3 rounded bg-[#2b2e32] p-3 sm:grid-cols-[auto_1fr_220px]"><input v-model="memberForm.employeeIds" type="checkbox" :value="employee.id" :disabled="employee.isDefaultAdmin"><span><b class="text-sm">{{ employee.name }}</b><small class="ml-2 text-[#929aa4]">{{ employee.jobTitle || '未设置职位' }}</small></span><span v-if="employee.isDefaultAdmin" class="deco-pill green">超级管理员 / 所有者</span><select v-else v-model="memberForm.roleIds[employee.id]" class="deco-input" :disabled="!memberForm.employeeIds.includes(employee.id)"><option value="">沿用组织角色</option><option v-for="role in roles" :key="role.id" :value="role.id">{{ role.name }}</option></select></div></div><p v-if="Object.keys(memberForm.errors).length" class="mt-3 text-sm text-red-400">{{ Object.values(memberForm.errors)[0] }}</p><button class="deco-button primary mt-5 w-full" :disabled="memberForm.processing">保存成员与角色</button></form></div>

        <div v-if="moduleStore" class="fixed inset-0 z-[100] grid place-items-center bg-black/65 p-4" @click.self="moduleStore = null"><form class="deco-card max-h-[90vh] w-full max-w-3xl overflow-auto" @submit.prevent="saveModules"><div class="sticky top-0 z-10 -mx-5 -mt-[18px] mb-4 flex items-center justify-between border-b border-[#3a3f45] bg-[#242424] px-5 py-4"><div><h2 class="deco-card-title !mb-1">{{ moduleStore.name }} · 功能模块</h2><p class="text-xs text-[#929aa4]">已启用 {{ moduleForm.paths.length }} / {{ totalModules }} 个子页面；关闭后侧边栏隐藏且接口禁止访问。</p></div><button type="button" @click="moduleStore = null">×</button></div><section v-for="group in moduleGroups" :key="group.title" class="mb-4 rounded-lg bg-[#181b1f] p-4"><div class="mb-3 flex items-center justify-between"><b>{{ group.title }}</b><div class="flex gap-2"><button type="button" class="text-xs text-[#6ca4f6]" @click="selectGroup(group, true)">全选</button><button type="button" class="text-xs text-[#929aa4]" @click="selectGroup(group, false)">清空</button></div></div><div class="grid gap-2 md:grid-cols-2"><label v-for="item in group.items" :key="item.path" class="flex items-center gap-3 rounded-md border border-[#343a42] bg-[#22262b] p-3"><input v-model="moduleForm.paths" type="checkbox" :value="item.path"><span class="min-w-0"><b class="block text-sm">{{ item.title }}</b><small class="font-mono text-[#7f8994]">{{ item.path }}</small></span></label></div></section><p v-if="Object.keys(moduleForm.errors).length" class="mt-3 text-sm text-red-400">{{ Object.values(moduleForm.errors)[0] }}</p><button class="deco-button primary sticky bottom-0 mt-2 w-full" :disabled="moduleForm.processing">保存功能模块</button></form></div>
    </div>
</template>
