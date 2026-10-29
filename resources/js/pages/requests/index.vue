<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, FilePlus2, Plus } from '@lucide/vue';

const props = defineProps<{
    requests?: {
        data: Array<{
            id: number;
            status: string | null;
            created_at: string | null;
            requested_by?: { fullname: string } | null;
            internal_beneficiary?: { fullname: string } | null;
            external_beneficiary?: string | null;
            details?: Array<{
                module?: { module_name: string } | null;
                access_type?: string | null;
                priority?: string | null;
            }>;
        }>;
        links: Array<{ url: string | null; label: string; active: boolean }>;
        current_page: number;
        last_page: number;
    };
}>();

function resolveBeneficiary(
    request: NonNullable<typeof props.requests>['data'][number],
) {
    return (
        request.internal_beneficiary?.fullname ??
        request.external_beneficiary ??
        request.requested_by?.fullname ??
        'Unknown'
    );
}

function resolveModules(
    request: NonNullable<typeof props.requests>['data'][number],
) {
    return (
        (request.details ?? [])
            .map((detail) => detail.module?.module_name ?? 'Unknown module')
            .filter(Boolean)
            .join(', ') || 'No modules'
    );
}

function statusTone(status: string | null): string {
    const value = status?.toLowerCase() ?? 'pending';

    if (value.includes('approved')) {
        return 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-200';
    }

    if (value.includes('reject') || value.includes('cancel')) {
        return 'bg-rose-50 text-rose-800 dark:bg-rose-950/50 dark:text-rose-200';
    }

    return 'bg-amber-50 text-amber-800 dark:bg-amber-950/50 dark:text-amber-200';
}
</script>

<template>
    <Head title="Access Requests" />

    <main class="mx-auto w-full max-w-6xl flex-1 space-y-7 p-4 sm:p-6 lg:p-8">
        <header
            class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between"
        >
            <div>
                <p
                    class="text-primary text-xs font-semibold tracking-[0.16em] uppercase"
                >
                    Access management
                </p>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight">
                    Access requests
                </h1>
                <p
                    class="text-muted-foreground mt-2 max-w-xl text-sm leading-6"
                >
                    Track submitted requests and follow each access decision.
                </p>
            </div>
            <Link
                href="/requests/create"
                class="bg-primary text-primary-foreground hover:bg-primary/90 focus-visible:ring-ring inline-flex min-h-11 items-center justify-center gap-2 self-start rounded-xl px-4 text-sm font-semibold shadow-sm transition focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none sm:self-auto"
            >
                <Plus class="size-4" />
                New request
            </Link>
        </header>

        <section
            v-if="!requests || requests.data.length === 0"
            class="bg-card rounded-2xl border border-dashed px-6 py-14 text-center shadow-sm"
        >
            <div
                class="bg-primary/10 text-primary mx-auto flex size-14 items-center justify-center rounded-2xl"
            >
                <FilePlus2 class="size-6" />
            </div>
            <h2 class="mt-5 text-lg font-semibold">No requests yet</h2>
            <p
                class="text-muted-foreground mx-auto mt-2 max-w-md text-sm leading-6"
            >
                Start an access request when you need an account or permissions
                for a system.
            </p>
            <Link
                href="/requests/create"
                class="bg-primary text-primary-foreground hover:bg-primary/90 mt-6 inline-flex min-h-10 items-center rounded-lg px-4 text-sm font-semibold transition"
            >
                Create your first request
            </Link>
        </section>

        <section v-else class="space-y-3" aria-label="Access requests">
            <article
                v-for="request in requests.data"
                :key="request.id"
                class="bg-card hover:border-primary/25 rounded-2xl border p-5 shadow-sm transition hover:shadow-md sm:p-6"
            >
                <div
                    class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between"
                >
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-3">
                            <h2 class="text-lg font-semibold tracking-tight">
                                Request #{{ request.id }}
                            </h2>
                            <span
                                class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold"
                                :class="statusTone(request.status)"
                            >
                                <span
                                    class="size-1.5 rounded-full bg-current"
                                />
                                {{ request.status ?? 'Pending' }}
                            </span>
                        </div>
                        <p class="text-muted-foreground mt-1.5 text-sm">
                            Submitted {{ request.created_at ?? '—' }}
                        </p>

                        <dl
                            class="mt-5 grid gap-x-8 gap-y-4 sm:grid-cols-2 xl:grid-cols-3"
                        >
                            <div>
                                <dt
                                    class="text-muted-foreground text-xs font-medium"
                                >
                                    Requester
                                </dt>
                                <dd class="mt-1 text-sm font-medium">
                                    {{
                                        request.requested_by?.fullname ??
                                        'Unknown'
                                    }}
                                </dd>
                            </div>
                            <div>
                                <dt
                                    class="text-muted-foreground text-xs font-medium"
                                >
                                    Beneficiary
                                </dt>
                                <dd class="mt-1 text-sm font-medium">
                                    {{ resolveBeneficiary(request) }}
                                </dd>
                            </div>
                            <div class="sm:col-span-2 xl:col-span-1">
                                <dt
                                    class="text-muted-foreground text-xs font-medium"
                                >
                                    Modules
                                </dt>
                                <dd
                                    class="mt-1 line-clamp-2 text-sm font-medium"
                                >
                                    {{ resolveModules(request) }}
                                </dd>
                            </div>
                        </dl>
                    </div>
                    <Link
                        :href="`/requests/${request.id}`"
                        class="hover:border-primary/40 hover:bg-primary/5 hover:text-primary focus-visible:ring-ring inline-flex min-h-10 shrink-0 items-center justify-center gap-2 self-start rounded-lg border px-3.5 text-sm font-semibold transition focus-visible:ring-2 focus-visible:outline-none"
                    >
                        View request
                        <ArrowRight class="size-4" />
                    </Link>
                </div>
            </article>
        </section>

        <nav
            v-if="requests && requests.last_page > 1"
            class="flex flex-wrap justify-center gap-2 pt-2"
            aria-label="Requests pagination"
        >
            <template v-for="(link, index) in requests.links" :key="index">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    class="bg-card hover:border-primary/40 hover:text-primary min-w-10 rounded-lg border px-3 py-2 text-center text-sm transition"
                    :class="{
                        'border-primary bg-primary text-primary-foreground hover:bg-primary/90 hover:text-primary-foreground':
                            link.active,
                    }"
                    :aria-current="link.active ? 'page' : undefined"
                >
                    {{ link.label }}
                </Link>
                <span
                    v-else
                    class="text-muted-foreground/50 min-w-10 rounded-lg border px-3 py-2 text-center text-sm"
                >
                    {{ link.label }}
                </span>
            </template>
        </nav>
    </main>
</template>
