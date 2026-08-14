<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type Employee = {
    id: number;
    name: string;
    username: string;
    email?: string;
    jobTitle?: string;
    status: string;
    isDefaultAdmin: boolean;
    roles: string[];
    roleIds: string[];
    stores: string[];
    storeIds: string[];
};
type Option = { id: string; name: string };

const props = defineProps<{
    employees: Employee[];
    roles: Option[];
    stores: Option[];
}>();
const search = ref('');
const status = ref('');
const showCreate = ref(false);
const editing = ref<Employee | null>(null);
const passwordTarget = ref<Employee | null>(null);
const filtered = computed(() =>
    props.employees.filter(
        (employee) =>
            (!search.value ||
                `${employee.name} ${employee.username} ${employee.email}`
                    .toLowerCase()
                    .includes(search.value.toLowerCase())) &&
            (!status.value || employee.status === status.value),
    ),
);
const activeCount = computed(
    () =>
        props.employees.filter((employee) => employee.status === 'ACTIVE')
            .length,
);
const multiStoreCount = computed(
    () =>
        props.employees.filter((employee) => employee.storeIds.length > 1)
            .length,
);

const createForm = useForm({
    name: '',
    email: '',
    password: '',
    jobTitle: '',
    roleId: '',
    storeIds: ['default-store'] as string[],
});
const editForm = useForm({
    name: '',
    jobTitle: '',
    status: 'ACTIVE',
    roleIds: [] as string[],
    storeIds: [] as string[],
});
const passwordForm = useForm({ password: '' });

const create = () =>
    createForm.post('/employees', {
        onSuccess: () => {
            showCreate.value = false;
            createForm.reset();
            createForm.storeIds = ['default-store'];
        },
    });
const openEdit = (employee: Employee) => {
    editing.value = employee;
    editForm.name = employee.name;
    editForm.jobTitle = employee.jobTitle || '';
    editForm.status = employee.status;
    editForm.roleIds = [...employee.roleIds];
    editForm.storeIds = [...employee.storeIds];
};
const saveEdit = () =>
    editing.value &&
    editForm.put(`/employees/${editing.value.id}`, {
        onSuccess: () => {
            editing.value = null;
            editForm.reset();
        },
    });
const openPassword = (employee: Employee) => {
    passwordTarget.value = employee;
    passwordForm.reset();
};
const resetPassword = () =>
    passwordTarget.value &&
    passwordForm.put(`/employees/${passwordTarget.value.id}/password`, {
        onSuccess: () => {
            passwordTarget.value = null;
            passwordForm.reset();
        },
    });
const toggle = (employee: Employee) =>
    router.put(`/employees/${employee.id}`, {
        status: employee.status === 'ACTIVE' ? 'DISABLED' : 'ACTIVE',
    });
const remove = (employee: Employee) => {
    if (confirm(`确认删除 ${employee.name}？`)) {
        router.delete(`/employees/${employee.id}`);
    }
};
</script>

<template>
    <Head title="人员档案管理" />
    <div class="deco-page">
        <div class="deco-metrics mb-4">
            <article class="deco-card deco-metric">
                <p class="deco-metric-label">组织账号</p>
                <p class="deco-metric-value">{{ employees.length }}</p>
                <p class="deco-metric-detail">统一登录身份</p>
            </article>
            <article class="deco-card deco-metric">
                <p class="deco-metric-label">启用账号</p>
                <p class="deco-metric-value">{{ activeCount }}</p>
                <p class="deco-metric-detail">可正常登录</p>
            </article>
            <article class="deco-card deco-metric">
                <p class="deco-metric-label">跨店成员</p>
                <p class="deco-metric-value">{{ multiStoreCount }}</p>
                <p class="deco-metric-detail">可访问两家及以上店铺</p>
            </article>
            <article class="deco-card deco-metric">
                <p class="deco-metric-label">超级管理员</p>
                <p class="deco-metric-value">
                    {{
                        employees.filter((employee) => employee.isDefaultAdmin)
                            .length
                    }}
                </p>
                <p class="deco-metric-detail">拥有全局管理权限</p>
            </article>
        </div>
        <section class="deco-card">
            <div class="deco-header !mb-6">
                <div>
                    <h1 class="deco-title">人员与访问管理</h1>
                    <p class="deco-subtitle">
                        组织身份统一管理；店铺访问和店铺角色可在店铺管理中进一步覆盖。
                    </p>
                </div>
                <button class="deco-button primary" @click="showCreate = true">
                    新增用户
                </button>
            </div>
            <div class="deco-toolbar">
                <input
                    v-model="search"
                    class="deco-input w-64"
                    placeholder="搜索姓名、用户名或邮箱"
                />
                <select v-model="status" class="deco-input">
                    <option value="">状态筛选</option>
                    <option value="ACTIVE">启用</option>
                    <option value="DISABLED">停用</option>
                </select>
            </div>
            <div class="deco-table-wrap">
                <table class="deco-table">
                    <thead>
                        <tr>
                            <th>人员</th>
                            <th>邮箱</th>
                            <th>状态</th>
                            <th>角色</th>
                            <th>可访问店铺</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="employee in filtered" :key="employee.id">
                            <td>
                                <div class="flex items-center gap-3">
                                    <span
                                        class="grid size-9 place-items-center rounded bg-blue-500/15 font-bold text-blue-400"
                                        >{{ employee.name.slice(0, 1) }}</span
                                    >
                                    <div>
                                        <b>{{ employee.name }}</b>
                                        <p class="text-xs text-[#929aa4]">
                                            {{ employee.username }}
                                        </p>
                                    </div>
                                </div>
                            </td>
                            <td>{{ employee.email || '—' }}</td>
                            <td>
                                <span
                                    class="deco-pill"
                                    :class="
                                        employee.status === 'ACTIVE'
                                            ? 'green'
                                            : 'red'
                                    "
                                    >{{
                                        employee.status === 'ACTIVE'
                                            ? '启用'
                                            : '停用'
                                    }}</span
                                >
                            </td>
                            <td>{{ employee.roles.join('、') || '未分配' }}</td>
                            <td>{{ employee.stores.join('、') || '—' }}</td>
                            <td>
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        class="deco-button"
                                        @click="openEdit(employee)"
                                    >
                                        编辑</button
                                    ><button
                                        class="deco-button"
                                        @click="openPassword(employee)"
                                    >
                                        重置密码</button
                                    ><button
                                        class="deco-button"
                                        :disabled="employee.isDefaultAdmin"
                                        @click="toggle(employee)"
                                    >
                                        {{
                                            employee.status === 'ACTIVE'
                                                ? '停用'
                                                : '启用'
                                        }}</button
                                    ><button
                                        v-if="!employee.isDefaultAdmin"
                                        class="deco-button text-red-400"
                                        @click="remove(employee)"
                                    >
                                        删除
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <div
            v-if="showCreate"
            class="fixed inset-0 z-[80] grid place-items-center bg-black/65 p-4"
            @click.self="showCreate = false"
        >
            <form class="deco-card w-full max-w-lg" @submit.prevent="create">
                <div class="flex items-center justify-between">
                    <h2 class="deco-card-title">新增用户</h2>
                    <button type="button" @click="showCreate = false">×</button>
                </div>
                <div class="grid gap-3">
                    <input
                        v-model="createForm.name"
                        class="deco-input"
                        placeholder="姓名"
                        required
                    /><input
                        v-model="createForm.email"
                        class="deco-input"
                        type="email"
                        placeholder="邮箱"
                        required
                    /><input
                        v-model="createForm.password"
                        class="deco-input"
                        type="password"
                        placeholder="初始密码（至少 8 位）"
                        required
                    /><input
                        v-model="createForm.jobTitle"
                        class="deco-input"
                        placeholder="职位"
                    /><select v-model="createForm.roleId" class="deco-input">
                        <option value="">暂不分配角色</option>
                        <option
                            v-for="role in roles"
                            :key="role.id"
                            :value="role.id"
                        >
                            {{ role.name }}
                        </option>
                    </select>
                    <fieldset>
                        <legend class="mb-2 text-sm">可访问店铺</legend>
                        <div class="grid grid-cols-2 gap-2">
                            <label
                                v-for="store in stores"
                                :key="store.id"
                                class="rounded bg-[#2b2e32] p-2 text-sm"
                                ><input
                                    v-model="createForm.storeIds"
                                    type="checkbox"
                                    :value="store.id"
                                />
                                {{ store.name }}</label
                            >
                        </div>
                    </fieldset>
                    <p
                        v-if="Object.keys(createForm.errors).length"
                        class="text-sm text-red-400"
                    >
                        {{ Object.values(createForm.errors)[0] }}
                    </p>
                    <button
                        class="deco-button primary"
                        :disabled="createForm.processing"
                    >
                        {{ createForm.processing ? '创建中...' : '创建用户' }}
                    </button>
                </div>
            </form>
        </div>

        <div
            v-if="editing"
            class="fixed inset-0 z-[80] grid place-items-center bg-black/65 p-4"
            @click.self="editing = null"
        >
            <form
                class="deco-card max-h-[90vh] w-full max-w-xl overflow-y-auto"
                @submit.prevent="saveEdit"
            >
                <div class="flex items-center justify-between">
                    <h2 class="deco-card-title">编辑人员</h2>
                    <button type="button" @click="editing = null">×</button>
                </div>
                <div class="grid gap-4">
                    <input
                        v-model="editForm.name"
                        class="deco-input"
                        placeholder="姓名"
                        required
                    /><input
                        v-model="editForm.jobTitle"
                        class="deco-input"
                        placeholder="职位"
                    /><select
                        v-model="editForm.status"
                        class="deco-input"
                        :disabled="editing.isDefaultAdmin"
                    >
                        <option value="ACTIVE">启用</option>
                        <option value="DISABLED">停用</option>
                    </select>
                    <fieldset>
                        <legend class="mb-2 text-sm font-bold">角色</legend>
                        <div class="grid grid-cols-2 gap-2">
                            <label
                                v-for="role in roles"
                                :key="role.id"
                                class="rounded bg-[#2b2e32] p-2 text-sm"
                                ><input
                                    v-model="editForm.roleIds"
                                    type="checkbox"
                                    :value="role.id"
                                />
                                {{ role.name }}</label
                            >
                        </div>
                    </fieldset>
                    <fieldset>
                        <legend class="mb-2 text-sm font-bold">
                            可访问店铺
                        </legend>
                        <div class="grid grid-cols-2 gap-2">
                            <label
                                v-for="store in stores"
                                :key="store.id"
                                class="rounded bg-[#2b2e32] p-2 text-sm"
                                ><input
                                    v-model="editForm.storeIds"
                                    type="checkbox"
                                    :value="store.id"
                                />
                                {{ store.name }}</label
                            >
                        </div>
                    </fieldset>
                    <p
                        v-if="Object.keys(editForm.errors).length"
                        class="text-sm text-red-400"
                    >
                        {{ Object.values(editForm.errors)[0] }}
                    </p>
                    <button
                        class="deco-button primary"
                        :disabled="editForm.processing"
                    >
                        保存人员信息
                    </button>
                </div>
            </form>
        </div>

        <div
            v-if="passwordTarget"
            class="fixed inset-0 z-[90] grid place-items-center bg-black/65 p-4"
            @click.self="passwordTarget = null"
        >
            <form
                class="deco-card w-full max-w-md"
                @submit.prevent="resetPassword"
            >
                <h2 class="deco-card-title">
                    重置 {{ passwordTarget.name }} 的密码
                </h2>
                <input
                    v-model="passwordForm.password"
                    class="deco-input w-full"
                    type="password"
                    minlength="8"
                    placeholder="新密码（至少 8 位）"
                    required
                />
                <p
                    v-if="Object.keys(passwordForm.errors).length"
                    class="mt-2 text-sm text-red-400"
                >
                    {{ Object.values(passwordForm.errors)[0] }}
                </p>
                <button
                    class="deco-button primary mt-4 w-full"
                    :disabled="passwordForm.processing"
                >
                    确认重置
                </button>
            </form>
        </div>
    </div>
</template>
