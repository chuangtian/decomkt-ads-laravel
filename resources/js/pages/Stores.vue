<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

type Store = { id: string; slug: string; name: string; timezone: string; status: string; memberCount: number; memberIds: number[] };
type Employee = { id: number; name: string; jobTitle?: string; isDefaultAdmin: boolean };
const props = defineProps<{ stores: Store[]; employees: Employee[] }>();
const visible = ref(false);
const editing = ref<Store | null>(null);
const memberStore = ref<Store | null>(null);
const form = useForm({ name: '', slug: '', timezone: 'America/Los_Angeles', status: 'ACTIVE' });
const memberForm = useForm({ employeeIds: [] as number[] });

const open = (store?: Store) => {
    editing.value = store || null;
    form.name = store?.name || '';
    form.slug = store?.slug || '';
    form.timezone = store?.timezone || 'America/Los_Angeles';
    form.status = store?.status || 'ACTIVE';
    visible.value = true;
};
const save = () => editing.value
    ? form.put(`/stores/${editing.value.id}`, { onSuccess: () => {
 visible.value = false; editing.value = null;
} })
    : form.post('/stores', { onSuccess: () => {
 visible.value = false; form.reset();
} });
const remove = (store: Store) => {
 if (confirm(`确认删除店铺 ${store.name}？`)) {
router.delete(`/stores/${store.id}`);
}
};
const openMembers = (store: Store) => {
 memberStore.value = store; memberForm.employeeIds = [...store.memberIds];
};
const saveMembers = () => memberStore.value && memberForm.put(`/stores/${memberStore.value.id}/members`, { onSuccess: () => {
 memberStore.value = null; memberForm.reset();
} });
</script>

<template>
    <Head title="店铺管理" />
    <div class="deco-page">
        <section class="deco-card"><div class="deco-header"><div><h1 class="deco-title">店铺管理</h1><p class="deco-subtitle">每个店铺的数据与凭证互相隔离；授权成员才可访问。</p></div><button class="deco-button primary" @click="open()">新建店铺</button></div>
            <article v-for="store in props.stores" :key="store.id" class="mb-3 flex items-center gap-4 rounded-lg bg-[#15191d] p-4"><div class="flex-1"><div class="flex items-center gap-2"><b>{{ store.name }}</b><span class="deco-pill" :class="store.status === 'ACTIVE' ? 'green' : 'red'">{{ store.status === 'ACTIVE' ? '启用' : '停用' }}</span></div><p class="mt-1 font-mono text-xs text-[#929aa4]">{{ store.slug }} · {{ store.timezone }} · {{ store.memberCount }} 名成员</p></div><button class="deco-button" @click="openMembers(store)">成员授权</button><button class="deco-button" @click="open(store)">编辑</button><button class="deco-button text-red-400" :disabled="store.id === 'default-store'" @click="remove(store)">删除</button></article>
        </section>

        <div v-if="visible" class="fixed inset-0 z-[80] grid place-items-center bg-black/65 p-4" @click.self="visible = false"><form class="deco-card w-full max-w-lg" @submit.prevent="save"><div class="flex items-center justify-between"><h2 class="deco-card-title">{{ editing ? '编辑店铺' : '新建店铺' }}</h2><button type="button" @click="visible = false">×</button></div><div class="grid gap-3"><input v-model="form.name" class="deco-input" placeholder="店铺名称" required><input v-model="form.slug" class="deco-input" placeholder="唯一标识" :disabled="!!editing"><input v-model="form.timezone" class="deco-input" placeholder="时区" required><select v-if="editing" v-model="form.status" class="deco-input"><option value="ACTIVE">启用</option><option value="DISABLED">停用</option></select><p v-if="Object.keys(form.errors).length" class="text-sm text-red-400">{{ Object.values(form.errors)[0] }}</p><button class="deco-button primary">保存店铺</button></div></form></div>

        <div v-if="memberStore" class="fixed inset-0 z-[90] grid place-items-center bg-black/65 p-4" @click.self="memberStore = null"><form class="deco-card w-full max-w-lg" @submit.prevent="saveMembers"><div class="flex items-center justify-between"><div><h2 class="deco-card-title !mb-1">{{ memberStore.name }} · 成员授权</h2><p class="text-xs text-[#929aa4]">系统管理员始终保留店铺所有者权限</p></div><button type="button" @click="memberStore = null">×</button></div><div class="mt-5 grid gap-2"><label v-for="employee in employees" :key="employee.id" class="flex items-center gap-3 rounded bg-[#2b2e32] p-3"><input v-model="memberForm.employeeIds" type="checkbox" :value="employee.id" :disabled="employee.isDefaultAdmin"><span class="flex-1"><b class="text-sm">{{ employee.name }}</b><small class="ml-2 text-[#929aa4]">{{ employee.jobTitle || '未设置职位' }}</small></span><span v-if="employee.isDefaultAdmin" class="deco-pill green">所有者</span></label></div><p v-if="Object.keys(memberForm.errors).length" class="mt-3 text-sm text-red-400">{{ Object.values(memberForm.errors)[0] }}</p><button class="deco-button primary mt-5 w-full" :disabled="memberForm.processing">保存成员授权</button></form></div>
    </div>
</template>
