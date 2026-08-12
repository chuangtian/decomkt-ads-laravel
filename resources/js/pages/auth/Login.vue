<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';

defineOptions({ layout: { title: '欢迎回来', description: '' } });
defineProps<{ status?: string; canResetPassword: boolean }>();
</script>
<template>
    <Head title="登录" />
    <div v-if="status" class="mb-4 text-center text-sm text-emerald-400">{{ status }}</div>
    <Form v-bind="store.form()" :reset-on-success="['password']" v-slot="{ errors, processing }" class="grid gap-5">
        <div><Input id="email" type="text" name="email" required autofocus autocomplete="username" placeholder="用户名 / 邮箱" class="h-11 rounded-xl border-[#535960] bg-[#171b1f] text-white placeholder:text-[#777f89]"/><InputError :message="errors.email" /></div>
        <div><PasswordInput id="password" name="password" required autocomplete="current-password" placeholder="密码" class="h-11 rounded-xl border-[#535960] bg-[#171b1f] text-white"/><InputError :message="errors.password" /></div>
        <Button type="submit" class="mt-1 h-11 w-full rounded-full bg-[#4582e6] font-bold text-white hover:bg-[#5c94ed]" :disabled="processing" data-test="login-button"><Spinner v-if="processing" />登录</Button>
    </Form>
</template>
