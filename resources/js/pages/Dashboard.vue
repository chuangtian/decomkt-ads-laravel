<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    metrics: Array<{ label: string; value: number | string; hint: string }>;
    systems: Array<{ name: string; status: string; detail: string }>;
    amazon: { sales: number; orders: number; adSpend: number };
}>();
</script>

<template>
    <Head title="总览仪表盘" />
    <div class="deco-page">
        <div class="deco-header">
            <div>
                <h1 class="deco-title">总览仪表盘</h1>
                <p class="deco-subtitle">电商核心数据概览</p>
            </div>
            <input
                class="deco-input deco-date"
                value="2026-08-05　-　2026-08-11"
                aria-label="日期范围"
            />
        </div>

        <section class="deco-card mb-4 border-t-2 !border-t-lime-500">
            <div class="mb-5 flex items-center justify-between">
                <h2 class="deco-card-title !mb-0">Shopify 独立站</h2>
                <Link href="/ecommerce/shopify" class="text-sm text-lime-400"
                    >查看详情 →</Link
                >
            </div>
            <div class="grid grid-cols-2 gap-8 md:grid-cols-4">
                <div
                    v-for="label in ['GMV', '订单数', '客单价', '日均 GMV']"
                    :key="label"
                >
                    <p class="deco-metric-label">{{ label }}</p>
                    <p
                        class="mt-3 text-2xl font-bold"
                        :class="
                            label === 'GMV' ? 'text-lime-400' : 'text-white'
                        "
                    >
                        —
                    </p>
                </div>
            </div>
        </section>

        <section class="deco-card border-t-2 !border-t-orange-500">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="deco-card-title !mb-0">亚马逊</h2>
                <Link href="/ecommerce/amazon" class="text-sm text-orange-400"
                    >查看详情 →</Link
                >
            </div>
            <div
                class="grid grid-cols-3 gap-4 rounded-md bg-[#2d3034] px-4 py-5"
            >
                <div>
                    <p class="deco-metric-label">销售额</p>
                    <b class="mt-2 block text-xl text-orange-400"
                        >${{ amazon.sales.toLocaleString() }}</b
                    >
                </div>
                <div>
                    <p class="deco-metric-label">订单数</p>
                    <b class="mt-2 block text-xl">{{
                        amazon.orders.toLocaleString()
                    }}</b>
                </div>
                <div>
                    <p class="deco-metric-label">广告花费</p>
                    <b class="mt-2 block text-xl"
                        >${{ amazon.adSpend.toLocaleString() }}</b
                    >
                </div>
            </div>
        </section>

        <section class="mt-4 grid gap-3 md:grid-cols-4">
            <div
                v-for="system in systems"
                :key="system.name"
                class="deco-card !p-4"
            >
                <div class="flex items-center justify-between">
                    <span class="text-sm">{{ system.name }}</span
                    ><span class="deco-pill green">{{ system.status }}</span>
                </div>
                <p class="mt-2 text-xs text-[#929aa4]">{{ system.detail }}</p>
            </div>
        </section>
    </div>
</template>
