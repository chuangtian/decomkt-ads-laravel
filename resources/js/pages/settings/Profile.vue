<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import InputError from '@/components/InputError.vue';
const page = usePage();
const user = computed(
    () =>
        page.props.auth.user as {
            name: string;
            email: string;
            email_verified_at?: string;
        },
);
const editing = ref(false);
</script>
<template>
    <Head title="个人资料" />
    <div class="deco-page">
        <div class="deco-header">
            <div class="flex items-center gap-4">
                <span
                    class="grid size-12 place-items-center rounded-lg bg-blue-500/20 text-xl text-blue-400"
                    >{{ user.name.slice(0, 1) }}</span
                >
                <div>
                    <h1 class="deco-title">{{ user.name }}</h1>
                    <p class="deco-subtitle">
                        查看并管理您的个人资料与账户安全
                    </p>
                </div>
            </div>
        </div>
        <section class="deco-card">
            <h2 class="deco-card-title">账户信息</h2>
            <div v-if="!editing" class="grid gap-6 md:grid-cols-3">
                <div>
                    <p class="deco-metric-label">登录账号</p>
                    <p class="mt-2 font-bold">admin</p>
                </div>
                <div>
                    <p class="deco-metric-label">电子邮箱</p>
                    <p class="mt-2 font-bold">{{ user.email }}</p>
                </div>
                <div>
                    <p class="deco-metric-label">账号状态</p>
                    <p class="mt-2">
                        <span class="deco-pill green">正常</span>
                    </p>
                </div>
                <div>
                    <p class="deco-metric-label">所属角色</p>
                    <p class="mt-2">
                        <span class="deco-pill">超级管理员（预设）</span>
                    </p>
                </div>
                <div>
                    <p class="deco-metric-label">密码管理</p>
                    <Link href="/account/security" class="deco-button mt-2"
                        >修改密码</Link
                    >
                </div>
                <div>
                    <p class="deco-metric-label">资料管理</p>
                    <button class="deco-button mt-2" @click="editing = true">
                        编辑资料
                    </button>
                </div>
            </div>
            <Form
                v-else
                v-bind="ProfileController.update.form()"
                v-slot="{ errors, processing }"
                class="grid max-w-xl gap-4"
                ><label class="text-sm"
                    >姓名<input
                        name="name"
                        :value="user.name"
                        class="deco-input mt-2 w-full"
                        required /></label
                ><InputError :message="errors.name" /><label class="text-sm"
                    >电子邮箱<input
                        name="email"
                        type="email"
                        :value="user.email"
                        class="deco-input mt-2 w-full"
                        required /></label
                ><InputError :message="errors.email" />
                <div class="flex gap-2">
                    <button class="deco-button primary" :disabled="processing">
                        保存资料</button
                    ><button
                        type="button"
                        class="deco-button"
                        @click="editing = false"
                    >
                        取消
                    </button>
                </div></Form
            >
        </section>
    </div>
</template>
