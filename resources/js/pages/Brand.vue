<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

type Sheet = {
    key: string;
    title: string;
    headers: string[];
    rows: string[][];
    secretCols: number[];
};
const props = defineProps<{
    wikiUrl: string;
    sheets: Sheet[];
    dataSync?: { syncedAt: string | null } | null;
}>();
const syncing = ref(false);
const refresh = () => {
    syncing.value = true;
    router.post(
        '/data-sync/refresh',
        { path: '/workspace/brand' },
        { preserveScroll: true, onFinish: () => (syncing.value = false) },
    );
};
const active = ref(props.sheets[0]?.key || 'overview');
const visibleSecrets = ref(new Set<string>());
const toggleSecret = (sheet: string, row: number, column: number) => {
    const key = `${sheet}:${row}:${column}`;
    const next = new Set(visibleSecrets.value);

    if (next.has(key)) {
        next.delete(key);
    } else {
        next.add(key);
    }

    visibleSecrets.value = next;
};
const isUrl = (value: string) => /^https?:\/\//.test(value);
</script>

<template>
    <Head title="品牌资料" />
    <div class="deco-page">
        <header class="deco-header">
            <div>
                <h1 class="deco-title">品牌资料</h1>
                <p class="deco-subtitle">
                    账号、密码、执照等团队共享资料 — 来源：飞书「品牌资料」表格
                </p>
            </div>
            <div class="deco-actions">
                <span v-if="dataSync?.syncedAt" class="text-xs text-[#929aa4]"
                    >最近同步：{{
                        new Date(dataSync.syncedAt).toLocaleString('zh-CN')
                    }}</span
                ><a
                    v-if="wikiUrl"
                    :href="wikiUrl"
                    target="_blank"
                    rel="noopener"
                    class="deco-button"
                    >在飞书打开</a
                ><button
                    class="deco-button primary"
                    :disabled="syncing"
                    @click="refresh"
                >
                    {{ syncing ? '同步中…' : '刷新' }}
                </button>
            </div>
        </header>
        <section class="deco-card">
            <p class="mb-4 text-xs text-[#929aa4]">
                ◉ 密码默认隐藏，点击眼睛图标查看。敏感信息请勿外传。
            </p>
            <div class="deco-tabs">
                <button
                    v-for="sheet in sheets"
                    :key="sheet.key"
                    class="deco-tab"
                    :class="{ active: active === sheet.key }"
                    @click="active = sheet.key"
                >
                    {{ sheet.title }}
                </button>
                <button class="deco-tab" disabled>营业执照</button>
            </div>
            <template v-for="sheet in sheets" :key="sheet.key">
                <div v-if="active === sheet.key" class="deco-table-wrap !mt-0">
                    <table class="deco-table">
                        <thead>
                            <tr>
                                <th
                                    v-for="(header, column) in sheet.headers"
                                    :key="column"
                                >
                                    {{ header || `列 ${column + 1}` }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(row, rowIndex) in sheet.rows"
                                :key="rowIndex"
                            >
                                <td
                                    v-for="(value, column) in row"
                                    :key="column"
                                >
                                    <template
                                        v-if="sheet.secretCols.includes(column)"
                                        ><span class="font-mono">{{
                                            visibleSecrets.has(
                                                `${sheet.key}:${rowIndex}:${column}`,
                                            )
                                                ? value
                                                : value
                                                  ? '••••••••'
                                                  : '—'
                                        }}</span
                                        ><button
                                            v-if="value"
                                            class="ml-2 text-blue-400"
                                            :aria-label="
                                                visibleSecrets.has(
                                                    `${sheet.key}:${rowIndex}:${column}`,
                                                )
                                                    ? '隐藏密码'
                                                    : '显示密码'
                                            "
                                            @click="
                                                toggleSecret(
                                                    sheet.key,
                                                    rowIndex,
                                                    column,
                                                )
                                            "
                                        >
                                            {{
                                                visibleSecrets.has(
                                                    `${sheet.key}:${rowIndex}:${column}`,
                                                )
                                                    ? '隐藏'
                                                    : '查看'
                                            }}
                                        </button></template
                                    >
                                    <a
                                        v-else-if="isUrl(value)"
                                        :href="value"
                                        target="_blank"
                                        rel="noopener"
                                        class="text-blue-400 hover:underline"
                                        >{{ value }}</a
                                    >
                                    <span
                                        v-else-if="value"
                                        :class="
                                            sheet.headers[column] === '类型' &&
                                            value.includes('敏感')
                                                ? 'deco-pill red'
                                                : ''
                                        "
                                        >{{ value }}</span
                                    ><span v-else class="text-[#727983]"
                                        >—</span
                                    >
                                </td>
                            </tr>
                            <tr v-if="!sheet.rows.length">
                                <td
                                    :colspan="sheet.headers.length || 1"
                                    class="text-center text-[#929aa4]"
                                >
                                    暂无数据
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>
        </section>
    </div>
</template>
