<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import DecisionActions from './DecisionActions.vue';

type ApprovalInboxItem = {
    id: number;
    step: 'module' | 'final';
    module: string;
    accessType: string;
    priority: string;
    justification: string | null;
    startDate: string | null;
    endDate: string | null;
    requester: string;
    beneficiary: string;
    submittedAt: string | null;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

defineProps<{
    approvals: {
        data: ApprovalInboxItem[];
        links: PaginationLink[];
        current_page: number;
        last_page: number;
    };
}>();
</script>

<template>
    <Head title="Approver Inbox" />

    <main class="max-w-5xl space-y-6 p-6">
        <header>
            <h1 class="text-2xl font-semibold">Approver Inbox</h1>
            <p class="mt-1 text-sm text-gray-600">
                Access requests assigned to you and awaiting your decision.
            </p>
        </header>

        <p
            v-if="approvals.data.length === 0"
            class="rounded-lg border p-6 text-sm text-gray-600"
        >
            You have no pending approvals.
        </p>

        <section v-else class="space-y-4" aria-label="Pending approvals">
            <article
                v-for="approval in approvals.data"
                :key="approval.id"
                class="space-y-4 rounded-lg border p-5"
            >
                <div
                    class="flex flex-wrap items-start justify-between gap-3"
                >
                    <div>
                        <h2 class="text-lg font-medium">
                            {{ approval.module }}
                        </h2>
                        <p class="text-sm text-gray-600">
                            {{ approval.accessType }} ·
                            {{ approval.priority }} priority
                        </p>
                    </div>
                    <span
                        class="rounded-full border px-3 py-1 text-sm capitalize"
                    >
                        {{ approval.step }} approval
                    </span>
                </div>

                <dl class="grid gap-3 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="font-medium">Requester</dt>
                        <dd>{{ approval.requester }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium">Beneficiary</dt>
                        <dd>{{ approval.beneficiary }}</dd>
                    </div>
                    <div v-if="approval.startDate">
                        <dt class="font-medium">Start date</dt>
                        <dd>{{ approval.startDate }}</dd>
                    </div>
                    <div v-if="approval.endDate">
                        <dt class="font-medium">End date</dt>
                        <dd>{{ approval.endDate }}</dd>
                    </div>
                    <div
                        v-if="approval.submittedAt"
                        class="sm:col-span-2"
                    >
                        <dt class="font-medium">Submitted</dt>
                        <dd>{{ approval.submittedAt }}</dd>
                    </div>
                </dl>

                <p v-if="approval.justification" class="text-sm">
                    <span class="font-medium">Justification:</span>
                    {{ approval.justification }}
                </p>

                <DecisionActions :approval-id="approval.id" />
            </article>
        </section>

        <nav
            v-if="approvals.last_page > 1"
            class="flex flex-wrap gap-2"
            aria-label="Approval inbox pages"
        >
            <template v-for="(link, index) in approvals.links" :key="index">
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
