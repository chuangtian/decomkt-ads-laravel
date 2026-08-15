<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

type Claim = {
    id: string;
    fullName: string | null;
    email: string;
    verificationMethod: string;
    hasEvidence: boolean;
    code: string | null;
    status: string;
    aiConfidence: number | null;
    aiReason: string | null;
    emailDeliveryStatus: string | null;
    createdAt: string | null;
};

const props = defineProps<{
    store: { id: string; name: string };
    installation: {
        installed: boolean;
        status: string;
        shopDomain: string | null;
        installedAt: string | null;
        uninstalledAt: string | null;
    };
    campaign: Record<string, any> | null;
    claims: Claim[];
    metrics: { issued: number; failed: number; pending: number; total: number };
    smtp: {
        source: 'system' | 'store';
        configured: boolean;
        host: string;
        port: string;
        scheme: string;
        username: string;
        password: string;
        fromAddress: string;
        fromName: string;
        hasPassword: boolean;
    };
    branding: {
        brandName: string;
        logoUrl: string | null;
        shopUrl: string;
        supportUrl: string;
        instagramUrl: string;
        facebookUrl: string;
        tiktokUrl: string;
        youtubeUrl: string;
    };
}>();

const tabs = ['申请审核', '折扣设置', '邮件设置', '数据分析', '帮助'];
const activeTab = ref(0);
const productIds = ref((props.campaign?.discountProductIds ?? []).join('\n'));
const collectionIds = ref(
    (props.campaign?.discountCollectionIds ?? []).join('\n'),
);
const settings = useForm({
    enabled: Boolean(props.campaign?.enabled ?? true),
    codePrefix: String(props.campaign?.codePrefix ?? 'STUDENT'),
    discountType: String(props.campaign?.discountType ?? 'PERCENTAGE'),
    discountValue: Number(props.campaign?.discountValue ?? 10),
    currencyCode: String(props.campaign?.currencyCode ?? 'USD'),
    discountTarget: String(props.campaign?.discountTarget ?? 'ALL_PRODUCTS'),
    discountProductIds: [] as string[],
    discountCollectionIds: [] as string[],
    usageLimit: Number(props.campaign?.usageLimit ?? 1),
    combinesWithProduct: Boolean(props.campaign?.combinesWithProduct ?? false),
    combinesWithOrder: Boolean(props.campaign?.combinesWithOrder ?? false),
    combinesWithShipping: Boolean(
        props.campaign?.combinesWithShipping ?? false,
    ),
});
const smtpForm = useForm({
    host: props.smtp.host,
    port: Number(props.smtp.port || 465),
    scheme: props.smtp.scheme || 'smtps',
    username: props.smtp.username,
    password: props.smtp.password,
    fromAddress: props.smtp.fromAddress,
    fromName: props.smtp.fromName || 'Macfox',
});
const brandingForm = useForm({
    brandName: props.branding.brandName,
    logo: null as File | null,
    shopUrl: props.branding.shopUrl,
    supportUrl: props.branding.supportUrl,
    instagramUrl: props.branding.instagramUrl,
    facebookUrl: props.branding.facebookUrl,
    tiktokUrl: props.branding.tiktokUrl,
    youtubeUrl: props.branding.youtubeUrl,
});
const logoInput = ref<HTMLInputElement | null>(null);
const logoPreview = ref<string | null>(props.branding.logoUrl);
let localLogoUrl: string | null = null;
watch(
    () => props.branding.logoUrl,
    (value) => {
        if (!brandingForm.logo) {
            logoPreview.value = value;
        }
    },
);
const statusLabel = computed(() => {
    if (props.installation.installed) {
        return '已安装';
    }

    if (props.installation.status === 'UNINSTALLED') {
        return '已卸载';
    }

    return '未安装';
});
const showSmtpPassword = ref(false);
const successNotice = ref('');
let successNoticeTimer: ReturnType<typeof setTimeout> | null = null;
const showSuccessNotice = (message: string) => {
    successNotice.value = message;

    if (successNoticeTimer) {
        clearTimeout(successNoticeTimer);
    }

    successNoticeTimer = setTimeout(() => {
        successNotice.value = '';
        successNoticeTimer = null;
    }, 3000);
};
const formatDate = (value: string | null) =>
    value ? new Date(value).toLocaleString('zh-CN') : '—';
const statusText: Record<string, string> = {
    PENDING: '待人工审核',
    ISSUED: '已发放',
    REJECTED: '已拒绝',
    FAILED: '失败',
};
const saveSettings = () => {
    const lines = (value: string) =>
        value
            .split(/[\n,]/)
            .map((item) => item.trim())
            .filter(Boolean);
    settings.discountProductIds = lines(productIds.value);
    settings.discountCollectionIds = lines(collectionIds.value);
    settings.put('/student-discounts/settings', {
        preserveScroll: true,
        onSuccess: () => showSuccessNotice('折扣设置保存成功'),
    });
};
const saveSmtp = () =>
    smtpForm.put('/student-discounts/smtp', {
        preserveScroll: true,
        onSuccess: () => showSuccessNotice('邮件设置保存成功'),
    });
const resetSmtp = () => {
    if (!window.confirm('确定恢复系统默认邮件配置吗？')) {
        return;
    }

    router.delete('/student-discounts/smtp', {
        preserveScroll: true,
        onSuccess: () => showSuccessNotice('已恢复系统默认邮件设置'),
    });
};
const chooseLogo = (event: Event) => {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;

    if (!file) {
        return;
    }

    if (localLogoUrl) {
        URL.revokeObjectURL(localLogoUrl);
    }

    brandingForm.logo = file;
    localLogoUrl = URL.createObjectURL(file);
    logoPreview.value = localLogoUrl;
};
const saveBranding = () =>
    brandingForm.post('/student-discounts/email-branding', {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            brandingForm.logo = null;

            if (logoInput.value) {
                logoInput.value.value = '';
            }

            showSuccessNotice('邮件品牌与链接保存成功');
        },
    });
const removeLogo = () => {
    brandingForm.logo = null;
    logoPreview.value = null;

    if (logoInput.value) {
        logoInput.value.value = '';
    }

    if (props.branding.logoUrl) {
        router.delete('/student-discounts/email-branding/logo', {
            preserveScroll: true,
            onSuccess: () => showSuccessNotice('邮件 Logo 已删除'),
        });
    }
};
</script>

<template>
    <Head title="学生折扣" />
    <div class="deco-page">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="translate-y-2 opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="translate-y-0 opacity-100"
            leave-to-class="translate-y-2 opacity-0"
        >
            <div
                v-if="successNotice"
                role="status"
                class="fixed top-6 right-6 z-[100] flex items-center gap-3 rounded-lg border border-emerald-600 bg-emerald-950 px-5 py-4 text-sm font-semibold text-emerald-200 shadow-2xl"
            >
                <span
                    class="flex h-6 w-6 items-center justify-center rounded-full bg-emerald-500 text-sm text-white"
                    >✓</span
                >
                {{ successNotice }}
            </div>
        </Transition>
        <div class="deco-header">
            <div>
                <h1 class="deco-title">学生折扣</h1>
                <p class="deco-subtitle">
                    当前店铺：{{ store.name }} · 页面数据来自本地 MySQL
                </p>
            </div>
            <div
                class="rounded-lg border px-4 py-3"
                :class="
                    installation.installed
                        ? 'border-emerald-700 bg-emerald-950/30'
                        : 'border-[#3c4148] bg-[#242628]'
                "
            >
                <p class="text-xs text-[#929aa4]">Shopify 安装状态</p>
                <p
                    class="mt-1 font-semibold"
                    :class="installation.installed ? 'text-emerald-400' : ''"
                >
                    {{ statusLabel }}
                </p>
                <p v-if="installation.shopDomain" class="mt-1 text-xs">
                    {{ installation.shopDomain }}
                </p>
            </div>
        </div>

        <div class="deco-tabs mb-4">
            <button
                v-for="(tab, index) in tabs"
                :key="tab"
                class="deco-tab"
                :class="{ active: activeTab === index }"
                @click="activeTab = index"
            >
                {{ tab }}
            </button>
        </div>

        <template v-if="activeTab === 0">
            <div class="mb-4 grid gap-3 md:grid-cols-4">
                <article
                    v-for="item in [
                        { label: '待人工审核', value: metrics.pending },
                        { label: '已发放', value: metrics.issued },
                        { label: '失败/拒绝', value: metrics.failed },
                        { label: '总申请', value: metrics.total },
                    ]"
                    :key="item.label"
                    class="deco-card deco-metric"
                >
                    <p class="deco-metric-label">{{ item.label }}</p>
                    <p class="deco-metric-value">{{ item.value }}</p>
                </article>
            </div>
            <section class="deco-card overflow-hidden !p-0">
                <div class="border-b border-[#35393f] p-5">
                    <h2 class="deco-card-title !mb-1">申请与审核</h2>
                    <p class="text-sm text-[#929aa4]">
                        待人工审核记录始终排在最前；通过或拒绝请在 Shopify
                        后台操作。
                    </p>
                </div>
                <div v-if="claims.length" class="overflow-x-auto">
                    <table class="w-full min-w-[980px] text-left text-sm">
                        <thead class="bg-[#2b2d30] text-[#aab2bc]">
                            <tr>
                                <th class="p-4">申请人</th>
                                <th class="p-4">方式</th>
                                <th class="p-4">证明</th>
                                <th class="p-4">折扣码</th>
                                <th class="p-4">状态</th>
                                <th class="p-4">AI 初筛</th>
                                <th class="p-4">邮件</th>
                                <th class="p-4">提交时间</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="claim in claims"
                                :key="claim.id"
                                class="border-t border-[#35393f]"
                            >
                                <td class="p-4">
                                    <b>{{ claim.fullName || '—' }}</b>
                                    <p class="text-xs text-[#929aa4]">
                                        {{ claim.email }}
                                    </p>
                                </td>
                                <td class="p-4">
                                    {{
                                        claim.verificationMethod ===
                                        'STUDENT_ID'
                                            ? '学生证'
                                            : '教育邮箱'
                                    }}
                                </td>
                                <td class="p-4">
                                    <a
                                        v-if="claim.hasEvidence"
                                        :href="`/student-discounts/claims/${claim.id}/evidence`"
                                        target="_blank"
                                        class="text-blue-400 hover:underline"
                                        >查看学生证</a
                                    ><span v-else>—</span>
                                </td>
                                <td class="p-4 font-mono">
                                    {{ claim.code || '—' }}
                                </td>
                                <td class="p-4">
                                    <span
                                        class="rounded-full bg-[#30343a] px-3 py-1 text-xs"
                                        >{{
                                            statusText[claim.status] ||
                                            claim.status
                                        }}</span
                                    >
                                </td>
                                <td class="max-w-64 p-4 text-xs text-[#aab2bc]">
                                    {{ claim.aiReason || '—' }}
                                    <span v-if="claim.aiConfidence !== null"
                                        >（{{
                                            Math.round(
                                                claim.aiConfidence * 100,
                                            )
                                        }}%）</span
                                    >
                                </td>
                                <td class="p-4">
                                    {{ claim.emailDeliveryStatus || '—' }}
                                </td>
                                <td class="p-4">
                                    {{ formatDate(claim.createdAt) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-else class="deco-empty">暂无申请记录</div>
            </section>
        </template>

        <form
            v-else-if="activeTab === 1"
            class="deco-card"
            @submit.prevent="saveSettings"
        >
            <h2 class="deco-card-title">折扣设置</h2>
            <p
                v-if="!installation.installed"
                class="mb-5 rounded border border-amber-700 bg-amber-950/30 p-3 text-sm text-amber-300"
            >
                当前店铺尚未安装插件，设置会保存在数据库中，安装完成后生效。
            </p>
            <div class="grid gap-5 lg:grid-cols-2">
                <label class="space-y-2"
                    ><span>折扣码前缀</span
                    ><input
                        v-model="settings.codePrefix"
                        class="deco-input w-full"
                /></label>
                <label class="space-y-2"
                    ><span>每个折扣码可使用次数</span
                    ><input
                        v-model.number="settings.usageLimit"
                        type="number"
                        min="1"
                        class="deco-input w-full"
                /></label>
                <label class="space-y-2"
                    ><span>折扣类型</span
                    ><select
                        v-model="settings.discountType"
                        class="deco-input w-full"
                    >
                        <option value="PERCENTAGE">百分比折扣</option>
                        <option value="FIXED_AMOUNT">固定金额折扣</option>
                    </select></label
                >
                <label class="space-y-2"
                    ><span>折扣值</span>
                    <div class="flex gap-2">
                        <input
                            v-model.number="settings.discountValue"
                            type="number"
                            min="0.01"
                            step="0.01"
                            class="deco-input w-full"
                        /><input
                            v-if="settings.discountType === 'FIXED_AMOUNT'"
                            v-model="settings.currencyCode"
                            maxlength="3"
                            class="deco-input w-28 uppercase"
                        /></div
                ></label>
                <label class="space-y-2 lg:col-span-2"
                    ><span>适用范围</span
                    ><select
                        v-model="settings.discountTarget"
                        class="deco-input w-full"
                    >
                        <option value="ALL_PRODUCTS">所有产品</option>
                        <option value="PRODUCTS">指定产品</option>
                        <option value="COLLECTIONS">指定产品系列</option>
                    </select></label
                >
                <label
                    v-if="settings.discountTarget === 'PRODUCTS'"
                    class="space-y-2 lg:col-span-2"
                    ><span>Shopify 产品 GID（每行一个）</span
                    ><textarea
                        v-model="productIds"
                        rows="5"
                        class="deco-input w-full font-mono text-xs"
                    />
                </label>
                <label
                    v-if="settings.discountTarget === 'COLLECTIONS'"
                    class="space-y-2 lg:col-span-2"
                    ><span>Shopify 产品系列 GID（每行一个）</span
                    ><textarea
                        v-model="collectionIds"
                        rows="5"
                        class="deco-input w-full font-mono text-xs"
                    />
                </label>
            </div>
            <div class="mt-6 border-t border-[#35393f] pt-5">
                <h3 class="mb-3 font-semibold">允许与其他折扣叠加</h3>
                <div class="flex flex-wrap gap-6">
                    <label class="flex items-center gap-2"
                        ><input
                            v-model="settings.combinesWithProduct"
                            type="checkbox"
                        />产品折扣</label
                    >
                    <label class="flex items-center gap-2"
                        ><input
                            v-model="settings.combinesWithOrder"
                            type="checkbox"
                        />订单折扣</label
                    >
                    <label class="flex items-center gap-2"
                        ><input
                            v-model="settings.combinesWithShipping"
                            type="checkbox"
                        />运费折扣</label
                    >
                </div>
            </div>
            <div
                v-if="Object.keys(settings.errors).length"
                class="mt-4 text-sm text-red-400"
            >
                {{ Object.values(settings.errors)[0] }}
            </div>
            <button
                class="deco-button primary mt-6"
                :disabled="settings.processing"
            >
                {{ settings.processing ? '保存中…' : '保存折扣设置' }}
            </button>
        </form>

        <div v-else-if="activeTab === 2" class="space-y-4">
            <form class="deco-card" @submit.prevent="saveSmtp">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="deco-card-title !mb-1">邮件设置</h2>
                        <p class="text-sm text-[#929aa4]">
                            当前来源：{{
                                smtp.source === 'store'
                                    ? '店铺独立配置'
                                    : '系统默认配置'
                            }}。店铺配置始终优先。
                        </p>
                    </div>
                    <span
                        class="rounded-full px-3 py-1 text-xs"
                        :class="
                            props.smtp.configured
                                ? 'bg-emerald-950 text-emerald-400'
                                : 'bg-amber-950 text-amber-300'
                        "
                        >{{ props.smtp.configured ? '已配置' : '未配置' }}</span
                    >
                </div>
                <div class="mt-6 grid gap-5 lg:grid-cols-2">
                    <label class="space-y-2"
                        ><span>SMTP 主机</span
                        ><input
                            v-model="smtpForm.host"
                            class="deco-input w-full"
                            placeholder="smtp.gmail.com"
                    /></label>
                    <label class="space-y-2"
                        ><span>端口</span
                        ><input
                            v-model.number="smtpForm.port"
                            type="number"
                            class="deco-input w-full"
                    /></label>
                    <label class="space-y-2"
                        ><span>连接方式</span
                        ><select
                            v-model="smtpForm.scheme"
                            class="deco-input w-full"
                        >
                            <option value="smtps">SMTPS / SSL</option>
                            <option value="smtp">SMTP / STARTTLS</option>
                        </select></label
                    >
                    <label class="space-y-2"
                        ><span>用户名</span
                        ><input
                            v-model="smtpForm.username"
                            class="deco-input w-full"
                    /></label>
                    <label class="space-y-2"
                        ><span>应用专用密码</span>
                        <div class="relative">
                            <input
                                v-model="smtpForm.password"
                                :type="showSmtpPassword ? 'text' : 'password'"
                                class="deco-input w-full pr-20"
                                placeholder="请输入应用专用密码"
                            />
                            <button
                                type="button"
                                class="absolute inset-y-0 right-3 text-sm text-blue-400 hover:text-blue-300"
                                @click="showSmtpPassword = !showSmtpPassword"
                            >
                                {{ showSmtpPassword ? '隐藏' : '显示' }}
                            </button>
                        </div></label
                    >
                    <label class="space-y-2"
                        ><span>发件人邮箱</span
                        ><input
                            v-model="smtpForm.fromAddress"
                            type="email"
                            class="deco-input w-full"
                    /></label>
                    <label class="space-y-2 lg:col-span-2"
                        ><span>发件人名称</span
                        ><input
                            v-model="smtpForm.fromName"
                            class="deco-input w-full"
                    /></label>
                </div>
                <div
                    v-if="Object.keys(smtpForm.errors).length"
                    class="mt-4 text-sm text-red-400"
                >
                    {{ Object.values(smtpForm.errors)[0] }}
                </div>
                <div class="mt-6 flex gap-3">
                    <button
                        class="deco-button primary"
                        :disabled="smtpForm.processing"
                    >
                        保存店铺邮件配置</button
                    ><button
                        v-if="props.smtp.source === 'store'"
                        type="button"
                        class="deco-button"
                        @click="resetSmtp"
                    >
                        恢复系统默认
                    </button>
                </div>
            </form>

            <form class="deco-card" @submit.prevent="saveBranding">
                <div>
                    <h2 class="deco-card-title !mb-1">邮件品牌与链接</h2>
                    <p class="text-sm text-[#929aa4]">
                        仅用于当前店铺。未设置的
                        Logo、按钮或链接区域不会出现在邮件中。
                    </p>
                </div>

                <div class="mt-6 grid gap-5 lg:grid-cols-2">
                    <label class="space-y-2 lg:col-span-2">
                        <span>品牌名称</span>
                        <input
                            v-model="brandingForm.brandName"
                            class="deco-input w-full"
                            placeholder="例如：Macfox"
                        />
                    </label>

                    <div class="space-y-2 lg:col-span-2">
                        <span class="block">邮件 Logo</span>
                        <input
                            ref="logoInput"
                            type="file"
                            accept="image/png,image/jpeg,image/webp"
                            class="hidden"
                            @change="chooseLogo"
                        />
                        <div
                            v-if="logoPreview"
                            class="relative inline-flex min-h-32 min-w-64 items-center justify-center rounded-lg border border-[#454b54] bg-white p-6"
                        >
                            <img
                                :src="logoPreview"
                                :alt="brandingForm.brandName || '邮件 Logo'"
                                class="max-h-20 max-w-64 object-contain"
                            />
                            <button
                                type="button"
                                aria-label="删除 Logo"
                                title="删除后重新上传"
                                class="absolute -top-3 -right-3 flex h-8 w-8 items-center justify-center rounded-full border border-[#59616b] bg-[#202326] text-xl leading-none text-white shadow hover:bg-red-600"
                                @click="removeLogo"
                            >
                                ×
                            </button>
                        </div>
                        <button
                            v-else
                            type="button"
                            class="flex min-h-32 w-full items-center justify-center rounded-lg border border-dashed border-[#59616b] text-[#aab2bc] hover:border-blue-400 hover:text-blue-400"
                            @click="logoInput?.click()"
                        >
                            点击上传 Logo（PNG、JPG 或 WebP，最大 2MB）
                        </button>
                    </div>

                    <label class="space-y-2">
                        <span>商店链接</span>
                        <input
                            v-model="brandingForm.shopUrl"
                            type="url"
                            class="deco-input w-full"
                            placeholder="https://example.com/"
                        />
                    </label>
                    <label class="space-y-2">
                        <span>客服链接</span>
                        <input
                            v-model="brandingForm.supportUrl"
                            class="deco-input w-full"
                            placeholder="mailto:support@example.com"
                        />
                    </label>
                    <label class="space-y-2">
                        <span>Instagram 链接</span>
                        <input
                            v-model="brandingForm.instagramUrl"
                            type="url"
                            class="deco-input w-full"
                            placeholder="https://instagram.com/..."
                        />
                    </label>
                    <label class="space-y-2">
                        <span>Facebook 链接</span>
                        <input
                            v-model="brandingForm.facebookUrl"
                            type="url"
                            class="deco-input w-full"
                            placeholder="https://facebook.com/..."
                        />
                    </label>
                    <label class="space-y-2">
                        <span>TikTok 链接</span>
                        <input
                            v-model="brandingForm.tiktokUrl"
                            type="url"
                            class="deco-input w-full"
                            placeholder="https://tiktok.com/@..."
                        />
                    </label>
                    <label class="space-y-2">
                        <span>YouTube 链接</span>
                        <input
                            v-model="brandingForm.youtubeUrl"
                            type="url"
                            class="deco-input w-full"
                            placeholder="https://youtube.com/@..."
                        />
                    </label>
                </div>

                <div
                    v-if="Object.keys(brandingForm.errors).length"
                    class="mt-4 text-sm text-red-400"
                >
                    {{ Object.values(brandingForm.errors)[0] }}
                </div>
                <button
                    class="deco-button primary mt-6"
                    :disabled="brandingForm.processing"
                >
                    {{
                        brandingForm.processing
                            ? '保存中…'
                            : '保存邮件品牌与链接'
                    }}
                </button>
            </form>
        </div>

        <template v-else-if="activeTab === 3">
            <div class="grid gap-3 md:grid-cols-4">
                <article
                    v-for="item in [
                        { label: '总申请', value: metrics.total },
                        { label: '已发放', value: metrics.issued },
                        { label: '待审核', value: metrics.pending },
                        { label: '失败/拒绝', value: metrics.failed },
                    ]"
                    :key="item.label"
                    class="deco-card deco-metric"
                >
                    <p class="deco-metric-label">{{ item.label }}</p>
                    <p class="deco-metric-value">{{ item.value }}</p>
                </article>
            </div>
            <section class="deco-card mt-4">
                <h2 class="deco-card-title">数据说明</h2>
                <p class="text-[#aab2bc]">
                    统计数据直接汇总自当前店铺的 MySQL
                    申请记录，不会在打开页面时访问 Shopify 或 AI 服务。
                </p>
            </section>
        </template>

        <section v-else class="deco-card space-y-5">
            <div>
                <h2 class="deco-card-title !mb-2">帮助</h2>
                <p class="text-[#aab2bc]">
                    插件安装状态优先由 Shopify webhook
                    写入数据库，定时任务负责补偿同步；确需实时确认时仅异步刷新对应模块，不阻塞整页。
                </p>
            </div>
            <div class="grid gap-4 md:grid-cols-3">
                <article class="rounded-lg bg-[#2b2d30] p-4">
                    <b>申请审核</b>
                    <p class="mt-2 text-sm text-[#aab2bc]">
                        AI 仅判断图片是否像学生证。可信度不足的申请在 Shopify
                        后台人工处理。
                    </p>
                </article>
                <article class="rounded-lg bg-[#2b2d30] p-4">
                    <b>邮件配置</b>
                    <p class="mt-2 text-sm text-[#aab2bc]">
                        未设置店铺 SMTP
                        时自动使用系统默认；密码加密保存在数据库中。
                    </p>
                </article>
                <article class="rounded-lg bg-[#2b2d30] p-4">
                    <b>多店铺隔离</b>
                    <p class="mt-2 text-sm text-[#aab2bc]">
                        安装、设置、申请、文件和统计全部按当前店铺隔离。
                    </p>
                </article>
            </div>
        </section>
    </div>
</template>
