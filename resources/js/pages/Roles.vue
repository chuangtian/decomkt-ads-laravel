<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { LockKeyhole, ShieldCheck, Users } from '@lucide/vue';
import { computed, ref } from 'vue';

type Permission = {
    id: string;
    name: string;
    module: string;
    description?: string;
};
type Role = {
    id: string;
    key: string;
    name: string;
    description?: string;
    isSystem: boolean;
    permissionIds: string[];
    employeeCount: number;
};
const props = defineProps<{ roles: Role[]; permissions: Permission[] }>();
const editing = ref<Role | null>(null);
const visible = ref(false);
const form = useForm({
    name: '',
    key: '',
    description: '',
    permissionIds: [] as string[],
});
const permissionGroups = computed(() =>
    Object.entries(
        Object.groupBy(props.permissions, (permission) => permission.module),
    ),
);
const assignedPeople = computed(() =>
    props.roles.reduce((sum, role) => sum + role.employeeCount, 0),
);

const open = (role?: Role) => {
    editing.value = role || null;
    form.name = role?.name || '';
    form.key = role?.key || '';
    form.description = role?.description || '';
    form.permissionIds = [...(role?.permissionIds || [])];
    visible.value = true;
};
const save = () =>
    editing.value
        ? form.put(`/roles/${editing.value.id}`, {
              onSuccess: () => {
                  visible.value = false;
              },
          })
        : form.post('/roles', {
              onSuccess: () => {
                  visible.value = false;
              },
          });
const remove = (role: Role) => {
    if (confirm(`确认删除角色 ${role.name}？`)) {
        router.delete(`/roles/${role.id}`);
    }
};
const setGroup = (permissions: Permission[] | undefined, checked: boolean) => {
    const selected = new Set(form.permissionIds);
    (permissions || []).forEach((permission) =>
        checked ? selected.add(permission.id) : selected.delete(permission.id),
    );
    form.permissionIds = [...selected];
};
</script>

<template>
    <Head title="角色与权限" />
    <div class="deco-page">
        <div class="deco-header">
            <div>
                <h1 class="deco-title">角色与权限</h1>
                <p class="deco-subtitle">
                    角色定义可操作页面；既可作为组织默认角色，也可单独分配给某家店铺的成员。
                </p>
            </div>
            <button class="deco-button primary" @click="open()">
                添加角色
            </button>
        </div>
        <div class="deco-metrics mb-4">
            <article class="deco-card deco-metric">
                <ShieldCheck class="mb-3 size-5 text-blue-400" />
                <p class="deco-metric-label">角色总数</p>
                <p class="deco-metric-value">{{ roles.length }}</p>
            </article>
            <article class="deco-card deco-metric">
                <LockKeyhole class="mb-3 size-5 text-emerald-400" />
                <p class="deco-metric-label">权限项</p>
                <p class="deco-metric-value">{{ permissions.length }}</p>
            </article>
            <article class="deco-card deco-metric">
                <Users class="mb-3 size-5 text-orange-400" />
                <p class="deco-metric-label">组织角色分配</p>
                <p class="deco-metric-value">{{ assignedPeople }}</p>
            </article>
            <article class="deco-card deco-metric">
                <ShieldCheck class="mb-3 size-5 text-purple-400" />
                <p class="deco-metric-label">系统预设</p>
                <p class="deco-metric-value">
                    {{ roles.filter((role) => role.isSystem).length }}
                </p>
            </article>
        </div>
        <section class="deco-card">
            <div class="space-y-3">
                <article
                    v-for="role in roles"
                    :key="role.id"
                    class="flex flex-wrap items-center gap-4 rounded-lg bg-[#15191d] p-4"
                >
                    <span
                        class="grid size-10 place-items-center rounded-lg bg-blue-500/10 text-blue-400"
                        ><ShieldCheck class="size-5"
                    /></span>
                    <div class="min-w-52 flex-1">
                        <div class="flex items-center gap-2">
                            <b>{{ role.name }}</b
                            ><span v-if="role.isSystem" class="deco-pill"
                                >系统预设</span
                            >
                        </div>
                        <p class="mt-1 text-xs text-[#929aa4]">
                            {{ role.key }} ·
                            {{ role.description || '未填写说明' }}
                        </p>
                    </div>
                    <span class="text-sm text-[#aab1ba]"
                        >{{ role.permissionIds.length }} 个权限 ·
                        {{ role.employeeCount }} 名组织成员</span
                    ><button class="deco-button" @click="open(role)">
                        配置权限</button
                    ><button
                        v-if="!role.isSystem"
                        class="deco-button text-red-400"
                        @click="remove(role)"
                    >
                        删除
                    </button>
                </article>
            </div>
        </section>

        <div
            v-if="visible"
            class="fixed inset-0 z-[100] grid place-items-center bg-black/65 p-4"
            @click.self="visible = false"
        >
            <form
                class="deco-card max-h-[90vh] w-full max-w-4xl overflow-auto"
                @submit.prevent="save"
            >
                <div
                    class="sticky top-0 z-10 -mx-5 -mt-[18px] mb-4 flex items-center justify-between border-b border-[#3a3f45] bg-[#242424] px-5 py-4"
                >
                    <div>
                        <h2 class="deco-card-title !mb-1">
                            {{ editing ? `配置 ${editing.name}` : '添加角色' }}
                        </h2>
                        <p class="text-xs text-[#929aa4]">
                            最终可见页面 = 店铺已启用模块 ∩ 当前成员角色权限
                        </p>
                    </div>
                    <button type="button" @click="visible = false">×</button>
                </div>
                <div class="grid gap-3 md:grid-cols-2">
                    <input
                        v-model="form.name"
                        class="deco-input"
                        placeholder="角色名称"
                        required
                    /><input
                        v-model="form.key"
                        class="deco-input"
                        placeholder="角色标识"
                        required
                        :disabled="editing?.isSystem"
                    /><input
                        v-model="form.description"
                        class="deco-input md:col-span-2"
                        placeholder="角色用途说明"
                    />
                </div>
                <h3 class="my-5 font-bold">页面权限</h3>
                <section
                    v-for="[module, permissionsInGroup] in permissionGroups"
                    :key="module"
                    class="mb-4 rounded-lg bg-[#181b1f] p-4"
                >
                    <div class="mb-3 flex items-center justify-between">
                        <b>{{ module }}</b>
                        <div class="flex gap-3">
                            <button
                                type="button"
                                class="text-xs text-blue-400"
                                @click="setGroup(permissionsInGroup, true)"
                            >
                                全选</button
                            ><button
                                type="button"
                                class="text-xs text-[#929aa4]"
                                @click="setGroup(permissionsInGroup, false)"
                            >
                                清空
                            </button>
                        </div>
                    </div>
                    <div class="grid gap-2 md:grid-cols-2 lg:grid-cols-3">
                        <label
                            v-for="permission in permissionsInGroup"
                            :key="permission.id"
                            class="flex items-start gap-2 rounded bg-[#2b2e32] p-3 text-sm"
                            ><input
                                v-model="form.permissionIds"
                                type="checkbox"
                                :value="permission.id"
                            /><span
                                ><b class="block">{{ permission.name }}</b
                                ><small class="font-mono text-[#7d8792]">{{
                                    permission.description
                                }}</small></span
                            ></label
                        >
                    </div>
                </section>
                <p
                    v-if="Object.keys(form.errors).length"
                    class="mt-3 text-sm text-red-400"
                >
                    {{ Object.values(form.errors)[0] }}
                </p>
                <button
                    class="deco-button primary sticky bottom-0 mt-2 w-full"
                    :disabled="form.processing"
                >
                    保存角色权限
                </button>
            </form>
        </div>
    </div>
</template>
