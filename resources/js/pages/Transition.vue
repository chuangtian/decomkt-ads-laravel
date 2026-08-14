<script setup lang="ts">
// Instant Inertia target used while the requested page is loading. The shared
// application layout stays mounted, so navigation feels immediate even when
// the page still needs fresh data from the server.
</script>

<template>
    <main class="deco-page" aria-busy="true" aria-label="页面加载中">
        <div class="mb-8 flex items-start justify-between gap-6">
            <div class="space-y-3">
                <div class="deco-route-skeleton h-8 w-44 rounded-md"></div>
                <div class="deco-route-skeleton h-4 w-72 max-w-[70vw] rounded"></div>
            </div>
            <div class="deco-route-skeleton h-10 w-28 rounded-md"></div>
        </div>

        <div class="mb-5 flex gap-3">
            <div v-for="index in 3" :key="index" class="deco-route-skeleton h-10 w-28 rounded-md"></div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div v-for="index in 4" :key="index" class="deco-card h-36">
                <div class="deco-route-skeleton mb-5 h-4 w-24 rounded"></div>
                <div class="deco-route-skeleton h-9 w-32 rounded"></div>
            </div>
        </div>

        <div class="mt-5 grid gap-4 xl:grid-cols-2">
            <div v-for="index in 2" :key="index" class="deco-card h-72">
                <div class="deco-route-skeleton mb-8 h-5 w-36 rounded"></div>
                <div class="flex h-44 items-end gap-3 px-5">
                    <div
                        v-for="bar in 8"
                        :key="bar"
                        class="deco-route-skeleton flex-1 rounded-t"
                        :style="{ height: `${28 + ((bar * 17) % 65)}%` }"
                    ></div>
                </div>
            </div>
        </div>
    </main>
</template>

<style scoped>
.deco-route-skeleton {
    background: linear-gradient(90deg, #282c31 25%, #343a42 50%, #282c31 75%);
    background-size: 220% 100%;
    animation: deco-route-loading 1.2s ease-in-out infinite;
}

@keyframes deco-route-loading {
    from { background-position: 100% 0; }
    to { background-position: -120% 0; }
}

@media (prefers-reduced-motion: reduce) {
    .deco-route-skeleton { animation: none; }
}
</style>
