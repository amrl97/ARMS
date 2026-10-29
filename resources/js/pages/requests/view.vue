<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';

type ApprovalRecord = {
    id: number;
    step: 'module' | 'final';
    status: 'pending' | 'approved' | 'rejected' | 'skipped';
    rejection_reason: string | null;
    created_at: string | null;
    updated_at: string | null;
    approver?: { fullname: string } | null;
};

type RequestDetail = {
    id: number;
    access_type: string;
    priority: string;
    justification: string | null;
    start_date: string | null;
    end_date: string | null;
    status: string;
    module?: { module_name: string } | null;
    approvals: ApprovalRecord[];
};

const props = defineProps<{
    accessRequest: {
        id: number;
        status: string;
        created_at: string | null;
        requested_by?: { fullname: string } | null;
        beneficiary: string;
        details: RequestDetail[];
        comments: Array<{
            id: number;
            comment: string;
            created_at: string | null;
            commented_by?: { fullname: string } | null;
        }>;
        attachments: Array<{
            id: number;
            file_name: string;
            file_size: number;
            created_at: string | null;
        }>;
    };
    canCancel: boolean;
}>();

function formatStatus(status: string): string {
    return status.replaceAll('_', ' ');
}

function formatSize(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    return `${(bytes / 1024).toFixed(1)} KB`;
}

function cancelRequest(): void {
    if (confirm('Cancel all pending lines in this request?')) {
        router.post(`/requests/${props.accessRequest.id}/cancel`);
    }
}
</script>

<template>
    <Head :title="`Request #${accessRequest.id} history`" />

    <main class="mx-auto max-w-5xl space-y-6 p-6">
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <Link href="/requests" class="text-sm underline">
                    Back to requests
                </Link>
                <h1 class="mt-2 text-2xl font-semibold">
                    Request #{{ accessRequest.id }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    Submitted {{ accessRequest.created_at ?? '—' }}
                </p>
            </div>
            <div class="flex items-center gap-3">
                <span class="rounded-full border px-3 py-1 text-sm capitalize">
                    {{ formatStatus(accessRequest.status) }}
                </span>
                <button
                    v-if="canCancel"
                    type="button"
                    class="rounded border border-destructive px-3 py-2 text-sm text-destructive"
                    @click="cancelRequest"
                >
                    Cancel pending lines
                </button>
            </div>
        </header>

        <section class="rounded-lg border p-4" aria-labelledby="request-parties">
            <h2 id="request-parties" class="text-lg font-medium">Request details</h2>
            <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-2">
                <div>
                    <dt class="font-medium">Requester</dt>
                    <dd>{{ accessRequest.requested_by?.fullname ?? 'Unknown' }}</dd>
                </div>
                <div>
                    <dt class="font-medium">Beneficiary</dt>
                    <dd>{{ accessRequest.beneficiary }}</dd>
                </div>
            </dl>
        </section>

        <section class="space-y-4" aria-labelledby="request-lines">
            <h2 id="request-lines" class="text-xl font-semibold">Requested access</h2>
            <article
                v-for="(detail, index) in accessRequest.details"
                :key="detail.id"
                class="space-y-5 rounded-lg border p-4"
            >
                <header class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-medium">
                            {{ detail.module?.module_name ?? `Module line ${index + 1}` }}
                        </h3>
                        <p class="text-sm text-muted-foreground">
                            {{ detail.access_type }} · {{ detail.priority }} priority
                        </p>
                    </div>
                    <span class="rounded-full border px-3 py-1 text-sm capitalize">
                        {{ formatStatus(detail.status) }}
                    </span>
                </header>

                <dl class="grid gap-3 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="font-medium">Justification</dt>
                        <dd>{{ detail.justification || 'Not provided' }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium">Access dates</dt>
                        <dd>
                            {{ detail.start_date || 'No start date' }}
                            –
                            {{ detail.end_date || 'No end date' }}
                        </dd>
                    </div>
                </dl>

                <section class="space-y-3" :aria-labelledby="`history-${detail.id}`">
                    <h4 :id="`history-${detail.id}`" class="font-medium">
                        Approval history
                    </h4>
                    <ol v-if="detail.approvals.length" class="space-y-3">
                        <li
                            v-for="approval in detail.approvals"
                            :key="approval.id"
                            class="rounded-md bg-muted/40 p-3 text-sm"
                        >
                            <div class="flex flex-wrap justify-between gap-2">
                                <p>
                                    <span class="font-medium capitalize">
                                        {{ approval.step }} approval
                                    </span>
                                    <span> · {{ approval.approver?.fullname ?? 'Unknown approver' }}</span>
                                </p>
                                <span class="capitalize">{{ approval.status }}</span>
                            </div>
                            <p class="mt-1 text-muted-foreground">
                                Assigned {{ approval.created_at ?? '—' }}
                                <template v-if="approval.status !== 'pending'">
                                    · Updated {{ approval.updated_at ?? '—' }}
                                </template>
                            </p>
                            <div
                                v-if="approval.rejection_reason"
                                class="mt-2 rounded border border-destructive/30 bg-destructive/5 p-2"
                            >
                                <p class="font-medium text-destructive">Rejection reason</p>
                                <p class="whitespace-pre-wrap">{{ approval.rejection_reason }}</p>
                            </div>
                        </li>
                    </ol>
                    <p v-else class="text-sm text-muted-foreground">
                        No approval assignments have been recorded.
                    </p>
                </section>
            </article>
        </section>

        <section class="space-y-3 rounded-lg border p-4" aria-labelledby="attachments-heading">
            <h2 id="attachments-heading" class="text-lg font-medium">Attachments</h2>
            <ul v-if="accessRequest.attachments.length" class="divide-y">
                <li
                    v-for="attachment in accessRequest.attachments"
                    :key="attachment.id"
                    class="flex flex-wrap items-center justify-between gap-2 py-3 text-sm"
                >
                    <div>
                        <a
                            :href="`/requests/${accessRequest.id}/attachments/${attachment.id}`"
                            class="font-medium underline"
                        >
                            {{ attachment.file_name }}
                        </a>
                        <p class="text-muted-foreground">
                            {{ formatSize(attachment.file_size) }}
                            · Added {{ attachment.created_at ?? '—' }}
                        </p>
                    </div>
                    <a
                        :href="`/requests/${accessRequest.id}/attachments/${attachment.id}`"
                        class="rounded border px-3 py-1.5"
                    >
                        Download
                    </a>
                </li>
            </ul>
            <p v-else class="text-sm text-muted-foreground">
                No attachments were submitted.
            </p>
        </section>

        <section class="space-y-3 rounded-lg border p-4" aria-labelledby="comments-heading">
            <h2 id="comments-heading" class="text-lg font-medium">Comments</h2>
            <ol v-if="accessRequest.comments.length" class="space-y-3">
                <li
                    v-for="comment in accessRequest.comments"
                    :key="comment.id"
                    class="rounded-md bg-muted/40 p-3"
                >
                    <div class="flex flex-wrap justify-between gap-2 text-sm">
                        <span class="font-medium">
                            {{ comment.commented_by?.fullname ?? 'Unknown user' }}
                        </span>
                        <time class="text-muted-foreground">{{ comment.created_at ?? '—' }}</time>
                    </div>
                    <p class="mt-2 whitespace-pre-wrap text-sm">{{ comment.comment }}</p>
                </li>
            </ol>
            <p v-else class="text-sm text-muted-foreground">
                No comments have been added.
            </p>
        </section>
    </main>
</template>