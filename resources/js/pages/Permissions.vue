<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
type Permission = {
    id: string;
    name: string;
    module: string;
    key: string;
    description?: string;
};
defineProps<{ permissions: Permission[] }>();
const form = useForm({ name: '', module: '', key: '', description: '' });
const create = () =>
    form.post('/permissions', { onSuccess: () => form.reset() });
const remove = (p: Permission) => {
    if (confirm(`删除权限 ${p.name}？`)) {
        router.delete(`/permissions/${p.id}`);
    }
};
</script>
<template>
    <Head title="页面访问权限" />
    <div class="deco-page">
        <section class="deco-card">
            <div class="deco-header">
                <div>
                    <h1 class="deco-title">页面访问权限</h1>
                    <p class="deco-subtitle">维护页面、分组和权限标识</p>
                </div>
            </div>
            <form class="deco-toolbar" @submit.prevent="create">
                <input
                    v-model="form.name"
                    class="deco-input"
                    placeholder="页面名称"
                    required
                /><input
                    v-model="form.module"
                    class="deco-input"
                    placeholder="所属分组"
                    required
                /><input
                    v-model="form.key"
                    class="deco-input"
                    placeholder="权限标识"
                    required
                /><input
                    v-model="form.description"
                    class="deco-input"
                    placeholder="页面路径"
                /><button class="deco-button primary">添加权限</button>
            </form>
            <div class="deco-table-wrap">
                <table class="deco-table">
                    <thead>
                        <tr>
                            <th>页面名称</th>
                            <th>所属分组</th>
                            <th>权限标识</th>
                            <th>页面路径</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="permission in permissions"
                            :key="permission.id"
                        >
                            <td>{{ permission.name }}</td>
                            <td>
                                <span class="deco-pill">{{
                                    permission.module
                                }}</span>
                            </td>
                            <td>
                                <code>{{ permission.key }}</code>
                            </td>
                            <td>{{ permission.description }}</td>
                            <td>
                                <button
                                    class="text-red-400"
                                    @click="remove(permission)"
                                >
                                    删除
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
