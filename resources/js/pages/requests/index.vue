<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps<{
    requests?: {
        data: Array<{
            id: number;
            status: string | null;
            created_at: string | null;
            requestedBy?: { fullname: string } | null;
            internalBeneficiary?: { fullname: string } | null;
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

function resolveBeneficiary(request: NonNullable<typeof props.requests>['data'][number]) {
    return request.internalBeneficiary?.fullname ?? request.external_beneficiary ?? request.requestedBy?.fullname ?? 'Unknown';
}

function resolveModules(request: NonNullable<typeof props.requests>['data'][number]) {
    return (request.details ?? [])
        .map((detail) => detail.module?.module_name ?? 'Unknown module')
        .filter(Boolean)
        .join(', ') || 'No modules';
}
</script>

<template>
    <Head title="Access Requests" />

    <main class="max-w-5xl space-y-6 p-6">
        <header class="flex items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold">Access requests</h1>
                <p class="mt-1 text-sm text-gray-600">
                    Review submitted access requests for the organisation.
                </p>
            </div>
            <Link
                href="/requests/create"
                class="rounded bg-slate-900 px-4 py-2 text-sm font-medium text-white"
            >
                New request
            </Link>
        </header>

        <p
            v-if="!requests || requests.data.length === 0"
            class="rounded-lg border bg-white p-6 text-sm text-gray-600"
        >
            No access requests have been submitted yet.
        </p>

        <section v-else class="space-y-4" aria-label="Access requests">
            <article
                v-for="request in requests.data"
                :key="request.id"
                class="rounded-lg border bg-white p-5"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-medium">
                            Request #{{ request.id }}
                        </h2>
                        <p class="text-sm text-gray-600">
                            Submitted {{ request.created_at ?? '—' }}
                        </p>
                    </div>
                    <span class="rounded-full border px-3 py-1 text-sm capitalize">
                        {{ request.status ?? 'Pending' }}
                    </span>
                </div>

                <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="font-medium">Requester</dt>
                        <dd>{{ request.requestedBy?.fullname ?? 'Unknown' }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium">Beneficiary</dt>
                        <dd>{{ resolveBeneficiary(request) }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="font-medium">Modules</dt>
                        <dd>{{ resolveModules(request) }}</dd>
                    </div>
                </dl>
            </article>
        </section>

        <nav
            v-if="requests && requests.last_page > 1"
            class="flex flex-wrap gap-2"
            aria-label="Requests pagination"
        >
            <template v-for="(link, index) in requests.links" :key="index">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    class="rounded border px-3 py-2 text-sm"
                    :class="{ 'bg-gray-100 font-medium': link.active }"
                    :aria-current="link.active ? 'page' : undefined"
                >
                    {{ link.label }}
                </Link>
                <span
                    v-else
                    class="rounded border px-3 py-2 text-sm text-gray-400"
                >
                    {{ link.label }}
                </span>
            </template>
        </nav>
    </main>
</template>
