<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Check, ShieldCheck, X } from '@lucide/vue';
import {
    Stepper,
    StepperDescription,
    StepperIndicator,
    StepperItem,
    StepperSeparator,
    StepperTitle,
    StepperTrigger,
} from '@/components/ui/stepper';
import DecisionActions from './DecisionActions.vue';

type ApprovalHistoryItem = {
    step: 'module' | 'final';
    status: string;
    approver: string | null;
    rejectionReason: string | null;
    updatedAt: string | null;
};

defineProps<{
    approval: {
        id: number;
        step: 'module' | 'final';
        status: string;
        requestId: number;
        requestStatus: string;
        submittedAt: string | null;
        module: string;
        accessType: string;
        priority: string;
        justification: string | null;
        startDate: string | null;
        endDate: string | null;
        requester: string;
        beneficiary: string;
        history: ApprovalHistoryItem[];
    };
}>();

function formatStatus(status: string): string {
    return status.replaceAll('_', ' ');
}
</script>

<template>
    <Head :title="`Approval: ${approval.module}`" />

    <main class="mx-auto w-full flex-1 space-y-6 p-4 sm:p-6 lg:p-8">
        <header
            class="relative isolate overflow-hidden rounded-3xl bg-gradient-to-br from-slate-950 via-slate-900 to-teal-950 px-6 py-7 text-white shadow-lg shadow-slate-950/10 sm:px-8 sm:py-9"
        >
            <div class="flex flex-wrap items-start justify-between gap-5">
                <div>
                    <Link
                        href="/approvals"
                        class="inline-flex items-center gap-2 text-sm text-slate-300 transition hover:text-white"
                    >
                        <ArrowLeft class="size-4" />
                        Back to approvals
                    </Link>
                    <div class="mt-5 flex items-center gap-3">
                        <span
                            class="hidden size-10 items-center justify-center rounded-2xl border border-white/15 bg-white/10 text-teal-100 sm:flex"
                        >
                            <ShieldCheck class="size-5" />
                        </span>
                        <div>
                            <p
                                class="text-sm font-medium text-teal-100 capitalize"
                            >
                                {{ approval.step }} approval
                            </p>
                            <h1
                                class="mt-1 text-2xl font-semibold tracking-tight sm:text-3xl"
                            >
                                {{ approval.module }}
                            </h1>
                        </div>
                    </div>
                </div>
                <span
                    class="rounded-full border border-white/20 bg-white/10 px-3 py-1 text-sm capitalize"
                >
                    {{ formatStatus(approval.status) }}
                </span>
            </div>
        </header>

        <div v-if="approval.history.length" aria-label="Approval progress">
            <Stepper class="flex w-full items-start gap-2 overflow-x-auto py-2">
                <StepperItem
                    v-for="(item, index) in approval.history"
                    :key="`${item.step}-${index}`"
                    :step="index + 1"
                    class="relative flex min-w-40 flex-1 flex-col items-center"
                >
                    <StepperTrigger>
                        <StepperIndicator
                            v-slot="{ step }"
                            class="bg-muted"
                            :class="{
                                'border-emerald-600 bg-emerald-600 text-white':
                                    item.status === 'approved',
                                'border-destructive bg-destructive text-white':
                                    item.status === 'rejected',
                                'border-primary bg-primary text-primary-foreground':
                                    item.status === 'pending',
                            }"
                        >
                            <Check
                                v-if="item.status === 'approved'"
                                class="size-4"
                            />
                            <X
                                v-else-if="item.status === 'rejected'"
                                class="size-4"
                            />
                            <span v-else>{{ step }}</span>
                        </StepperIndicator>
                    </StepperTrigger>

                    <StepperSeparator
                        v-if="index < approval.history.length - 1"
                        class="bg-border absolute top-4 right-[calc(-50%+20px)] left-[calc(50%+20px)] block h-0.5 shrink-0 rounded-full"
                        :class="{
                            'bg-emerald-600': item.status === 'approved',
                            'bg-destructive': item.status === 'rejected',
                        }"
                    />

                    <div
                        class="mt-3 flex w-full flex-col items-center px-2 text-center"
                    >
                        <StepperTitle class="capitalize">
                            {{ item.step }} approval
                        </StepperTitle>
                        <StepperDescription>
                            {{ item.approver ?? 'Approver unavailable' }}
                            <span v-if="item.updatedAt">
                                · {{ item.updatedAt }}
                            </span>
                        </StepperDescription>
                        <span
                            class="bg-muted text-muted-foreground mt-2 rounded-full px-3 py-1 text-xs font-medium capitalize"
                            :class="{
                                'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200':
                                    item.status === 'approved',
                                'bg-destructive/10 text-destructive':
                                    item.status === 'rejected',
                            }"
                        >
                            {{ formatStatus(item.status) }}
                        </span>
                        <p
                            v-if="item.rejectionReason"
                            class="text-destructive mt-2 text-left text-xs"
                        >
                            Rejection reason: {{ item.rejectionReason }}
                        </p>
                    </div>
                </StepperItem>
            </Stepper>
        </div>
        <p v-else class="text-muted-foreground text-sm">
            No approval history is available.
        </p>

        <section
            class="bg-card space-y-5 rounded-2xl border p-5 shadow-sm sm:p-6"
        >
            <header
                class="flex flex-wrap items-start justify-between gap-3 border-b pb-4"
            >
                <div>
                    <h2 class="text-lg font-semibold">Request details</h2>
                    <p class="text-muted-foreground mt-1 text-sm">
                        Request #{{ approval.requestId }} · Submitted
                        {{ approval.submittedAt ?? '—' }}
                    </p>
                </div>
                <span
                    class="bg-muted text-muted-foreground rounded-full px-3 py-1 text-xs font-medium capitalize"
                >
                    Request {{ formatStatus(approval.requestStatus) }}
                </span>
            </header>

            <dl class="grid gap-x-8 gap-y-5 sm:grid-cols-2">
                <div>
                    <dt class="text-muted-foreground text-xs font-medium">
                        Requester
                    </dt>
                    <dd class="mt-1 text-sm font-medium">
                        {{ approval.requester }}
                    </dd>
                </div>
                <div>
                    <dt class="text-muted-foreground text-xs font-medium">
                        Beneficiary
                    </dt>
                    <dd class="mt-1 text-sm font-medium">
                        {{ approval.beneficiary }}
                    </dd>
                </div>
                <div>
                    <dt class="text-muted-foreground text-xs font-medium">
                        Access type
                    </dt>
                    <dd class="mt-1 text-sm font-medium">
                        {{ approval.accessType }}
                    </dd>
                </div>
                <div>
                    <dt class="text-muted-foreground text-xs font-medium">
                        Priority
                    </dt>
                    <dd class="mt-1 text-sm font-medium">
                        {{ approval.priority }}
                    </dd>
                </div>
                <div>
                    <dt class="text-muted-foreground text-xs font-medium">
                        Start date
                    </dt>
                    <dd class="mt-1 text-sm font-medium">
                        {{ approval.startDate ?? 'Not specified' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-muted-foreground text-xs font-medium">
                        End date
                    </dt>
                    <dd class="mt-1 text-sm font-medium">
                        {{ approval.endDate ?? 'Not specified' }}
                    </dd>
                </div>
            </dl>

            <div>
                <h3 class="text-muted-foreground text-xs font-medium">
                    Justification
                </h3>
                <p
                    class="bg-muted/60 mt-1.5 rounded-xl px-4 py-3 text-sm leading-6"
                >
                    {{ approval.justification || 'No justification provided.' }}
                </p>
            </div>
        </section>

        <section
            class="rounded-2xl bg-transparent p-5 sm:p-6"
            aria-labelledby="approval-decision"
        >
            <DecisionActions :approval-id="approval.id" class="mt-4" />
        </section>
    </main>
</template>
