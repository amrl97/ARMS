<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Activity, ChartNoAxesCombined, Clock3 } from '@lucide/vue';
import { dashboard } from '@/routes';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});

const chartPanels = [
    {
        title: 'Access request trends',
        description: 'Request activity over time',
        icon: Activity,
    },
    {
        title: 'Requests by status',
        description: 'A breakdown of current request progress',
        icon: ChartNoAxesCombined,
    },
];
</script>

<template>
    <Head title="Dashboard" />

    <main class="mx-auto w-full max-w-7xl flex-1 space-y-8 p-4 sm:p-6 lg:p-8">
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p
                    class="text-primary text-xs font-semibold tracking-[0.16em] uppercase"
                >
                    System insights
                </p>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight">
                    Dashboard
                </h1>
                <p
                    class="text-muted-foreground mt-2 max-w-2xl text-sm leading-6"
                >
                    A live overview of access activity and approval progress.
                    Charts will appear here once system analytics are connected.
                </p>
            </div>
            <div
                class="bg-card text-muted-foreground inline-flex items-center gap-2 rounded-full border px-3 py-2 text-xs font-medium shadow-sm"
            >
                <Clock3 class="text-primary size-4" />
                Analytics coming soon
            </div>
        </header>

        <section
            class="grid gap-5 lg:grid-cols-2"
            aria-label="System analytics charts"
        >
            <article
                v-for="panel in chartPanels"
                :key="panel.title"
                class="bg-card overflow-hidden rounded-2xl border shadow-sm"
            >
                <header class="border-b px-5 py-4 sm:px-6">
                    <div class="flex items-start gap-3">
                        <div
                            class="bg-primary/10 text-primary flex size-10 shrink-0 items-center justify-center rounded-xl"
                        >
                            <component :is="panel.icon" class="size-5" />
                        </div>
                        <div>
                            <h2 class="font-semibold tracking-tight">
                                {{ panel.title }}
                            </h2>
                            <p class="text-muted-foreground mt-1 text-sm">
                                {{ panel.description }}
                            </p>
                        </div>
                    </div>
                </header>
                <div
                    class="flex min-h-72 flex-col items-center justify-center px-6 py-10 text-center"
                >
                    <div
                        class="bg-muted/50 text-muted-foreground mb-4 flex size-14 items-center justify-center rounded-2xl border border-dashed"
                    >
                        <component :is="panel.icon" class="size-6" />
                    </div>
                    <p class="font-medium">Chart data will be shown here</p>
                    <p
                        class="text-muted-foreground mt-1 max-w-xs text-sm leading-5"
                    >
                        This chart will be connected to live access request data
                        in a future update.
                    </p>
                </div>
            </article>
        </section>
    </main>
</template>
