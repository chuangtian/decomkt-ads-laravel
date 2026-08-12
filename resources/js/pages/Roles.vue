<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
type Permission={id:string;name:string;module:string;description?:string}; type Role={id:string;key:string;name:string;description?:string;isSystem:boolean;permissionIds:string[];employeeCount:number};
defineProps<{roles:Role[];permissions:Permission[]}>(); const editing=ref<Role|null>(null); const visible=ref(false);
const form=useForm({name:'',key:'',description:'',permissionIds:[] as string[]});
const open=(role?:Role)=>{
editing.value=role||null;form.name=role?.name||'';form.key=role?.key||'';form.description=role?.description||'';form.permissionIds=[...(role?.permissionIds||[])];visible.value=true;
};
const save=()=>editing.value?form.put(`/roles/${editing.value.id}`,{onSuccess:()=>visible.value=false}):form.post('/roles',{onSuccess:()=>visible.value=false});
const remove=(role:Role)=>{
if(confirm(`确认删除角色 ${role.name}？`)){
router.delete(`/roles/${role.id}`)
}
};
</script>
<template><Head title="系统角色配置"/><div class="deco-page"><section class="deco-card"><div class="deco-header"><div><h1 class="deco-title">系统角色配置</h1><p class="deco-subtitle">角色与页面权限管理</p></div><button class="deco-button primary" @click="open()">添加角色</button></div><div class="space-y-3"><article v-for="role in roles" :key="role.id" class="flex items-center gap-4 rounded-lg bg-[#15191d] p-4"><div class="min-w-0 flex-1"><div class="flex items-center gap-2"><b>{{role.name}}</b><span v-if="role.isSystem" class="deco-pill">预设</span></div><p class="mt-1 text-xs text-[#929aa4]">{{role.key}} · {{role.description}}</p></div><span class="text-sm text-[#aab1ba]">{{role.permissionIds.length}} 个权限 · {{role.employeeCount}} 人</span><button class="deco-button" @click="open(role)">编辑</button><button v-if="!role.isSystem" class="deco-button text-red-400" @click="remove(role)">删除</button></article></div></section><div v-if="visible" class="fixed inset-0 z-[80] grid place-items-center bg-black/65 p-4" @click.self="visible=false"><form class="deco-card max-h-[85vh] w-full max-w-3xl overflow-auto" @submit.prevent="save"><div class="flex items-center justify-between"><h2 class="deco-card-title">{{editing?'编辑角色':'添加角色'}}</h2><button type="button" @click="visible=false">×</button></div><div class="grid gap-3 md:grid-cols-2"><input v-model="form.name" class="deco-input" placeholder="角色名称" required><input v-model="form.key" class="deco-input" placeholder="角色标识" required :disabled="editing?.isSystem"><input v-model="form.description" class="deco-input md:col-span-2" placeholder="说明"></div><h3 class="my-4 font-bold">页面权限</h3><div class="grid gap-2 md:grid-cols-3"><label v-for="permission in permissions" :key="permission.id" class="flex items-center gap-2 rounded bg-[#2b2e32] p-2 text-sm"><input v-model="form.permissionIds" type="checkbox" :value="permission.id"><span>{{permission.module}} · {{permission.name}}</span></label></div><p v-if="Object.keys(form.errors).length" class="mt-3 text-sm text-red-400">{{Object.values(form.errors)[0]}}</p><div class="mt-5 flex justify-end"><button class="deco-button primary" :disabled="form.processing">保存角色</button></div></form></div></div></template>
