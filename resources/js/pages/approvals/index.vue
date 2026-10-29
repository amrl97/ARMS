<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, Inbox, ShieldCheck } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
    Table,
    TableBody,
    TableCell,
    TableEmpty,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { ApprovalQueue } from './types';

defineProps<{
    moduleApprovals: ApprovalQueue;
    finalApprovals: ApprovalQueue;
}>();
</script>

<template>
    <Head title="Approval Inbox" />

    <main class="mx-auto w-full flex-1 space-y-8 p-4 sm:p-6 lg:p-8">
        <header
            class="relative isolate overflow-hidden rounded-3xl bg-gradient-to-br from-slate-950 via-slate-900 to-teal-950 px-6 py-7 text-white shadow-lg shadow-slate-950/10 sm:px-8 sm:py-9"
        >
            <div class="flex items-start gap-4">
                <div
                    class="hidden size-10 shrink-0 items-center justify-center rounded-2xl border border-white/15 bg-white/10 text-teal-100 sm:flex"
                >
                    <ShieldCheck class="size-5" />
                </div>
                <div>
                    <h1 class="mt-2 text-3xl font-semibold tracking-tight">
                        Approval inbox
                    </h1>
                    <p class="mt-2 max-w-xl text-sm leading-6 text-slate-300">
                        Pending decisions assigned to your account, organised by
                        approval stage.
                    </p>
                </div>
            </div>
        </header>

        <Tabs default-value="module" class="space-y-5">
            <TabsList
                variant="line"
                class="w-full justify-start border-b"
                aria-label="Approval stages"
            >
                <TabsTrigger value="module" class="gap-2 px-4">
                    Module Approvals
                    <span
                        class="bg-muted text-muted-foreground rounded-full px-2 py-0.5 text-xs font-medium"
                    >
                        {{ moduleApprovals.data.length }}
                    </span>
                </TabsTrigger>
                <TabsTrigger value="final" class="gap-2 px-4">
                    Final Approvals
                    <span
                        class="bg-muted text-muted-foreground rounded-full px-2 py-0.5 text-xs font-medium"
                    >
                        {{ finalApprovals.data.length }}
                    </span>
                </TabsTrigger>
            </TabsList>

            <TabsContent value="module" class="mt-0">
                <section class="space-y-4" aria-labelledby="module-approvals">
                    <header
                        class="flex flex-wrap items-end justify-between gap-3"
                    >
                        <div>
                            <div class="flex items-center gap-2.5">
                                <Inbox class="text-primary size-5" />
                                <h2
                                    id="module-approvals"
                                    class="text-xl font-semibold tracking-tight"
                                >
                                    Module Approvals
                                </h2>
                            </div>
                            <p class="text-muted-foreground mt-1.5 text-sm">
                                Requests assigned to you for module-level
                                review.
                            </p>
                        </div>
                        <span
                            class="bg-muted text-muted-foreground rounded-full px-3 py-1 text-xs font-semibold"
                        >
                            {{ moduleApprovals.data.length }} on this page
                        </span>
                    </header>

                    <div
                        class="bg-card overflow-hidden rounded-2xl border shadow-sm"
                    >
                        <Table class="min-w-[900px]">
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Module</TableHead>
                                    <TableHead>Access</TableHead>
                                    <TableHead>Requester</TableHead>
                                    <TableHead>Beneficiary</TableHead>
                                    <TableHead>Priority</TableHead>
                                    <TableHead>Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableEmpty
                                    v-if="moduleApprovals.data.length === 0"
                                    :colspan="6"
                                    class="text-muted-foreground"
                                >
                                    <div class="text-center">
                                        <Inbox
                                            class="text-muted-foreground mx-auto size-6"
                                        />
                                        <p class="mt-3 font-medium">
                                            All caught up
                                        </p>
                                        <p class="mt-1 text-sm">
                                            No pending module approvals.
                                        </p>
                                    </div>
                                </TableEmpty>
                                <TableRow
                                    v-for="approval in moduleApprovals.data"
                                    :key="approval.id"
                                >
                                    <TableCell class="whitespace-normal">
                                        <p class="font-medium">
                                            {{ approval.module }}
                                        </p>
                                        <p
                                            v-if="approval.justification"
                                            class="text-muted-foreground mt-1 max-w-xs text-xs"
                                        >
                                            {{ approval.justification }}
                                        </p>
                                    </TableCell>
                                    <TableCell>
                                        {{ approval.accessType }}
                                    </TableCell>
                                    <TableCell>{{
                                        approval.requester
                                    }}</TableCell>
                                    <TableCell>
                                        {{ approval.beneficiary }}
                                    </TableCell>
                                    <TableCell>
                                        <Badge
                                            :variant="
                                                approval.priority === 'High'
                                                    ? 'high'
                                                    : approval.priority ===
                                                        'Medium'
                                                      ? 'medium'
                                                      : 'low'
                                            "
                                            class="min-w-20"
                                        >
                                            {{ approval.priority }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        <Link
                                            :href="`/approvals/${approval.id}`"
                                            class="text-primary hover:text-primary/80 inline-flex items-center gap-1.5 text-sm font-semibold transition"
                                        >
                                            View approval
                                            <ArrowRight class="size-4" />
                                        </Link>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>

                    <nav
                        v-if="moduleApprovals.last_page > 1"
                        class="flex flex-wrap justify-center gap-2 pt-2"
                        aria-label="Module approval pages"
                    >
                        <template
                            v-for="(link, index) in moduleApprovals.links"
                            :key="index"
                        >
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
                </section>
            </TabsContent>

            <TabsContent value="final" class="mt-0">
                <section class="space-y-4" aria-labelledby="final-approvals">
                    <header
                        class="flex flex-wrap items-end justify-between gap-3"
                    >
                        <div>
                            <div class="flex items-center gap-2.5">
                                <ShieldCheck class="text-primary size-5" />
                                <h2
                                    id="final-approvals"
                                    class="text-xl font-semibold tracking-tight"
                                >
                                    Final Approvals
                                </h2>
                            </div>
                            <p class="text-muted-foreground mt-1.5 text-sm">
                                Requests that requires final IT Admin approval.
                            </p>
                        </div>
                        <span
                            class="bg-muted text-muted-foreground rounded-full px-3 py-1 text-xs font-semibold"
                        >
                            {{ finalApprovals.data.length }} on this page
                        </span>
                    </header>

                    <div
                        class="bg-card overflow-hidden rounded-2xl border shadow-sm"
                    >
                        <Table class="min-w-[900px]">
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Module</TableHead>
                                    <TableHead>Access</TableHead>
                                    <TableHead>Requester</TableHead>
                                    <TableHead>Beneficiary</TableHead>
                                    <TableHead>Priority</TableHead>
                                    <TableHead>Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableEmpty
                                    v-if="finalApprovals.data.length === 0"
                                    :colspan="6"
                                    class="text-muted-foreground"
                                >
                                    <div class="text-center">
                                        <Inbox
                                            class="text-muted-foreground mx-auto size-6"
                                        />
                                        <p class="mt-3 font-medium">
                                            All caught up
                                        </p>
                                        <p class="mt-1 text-sm">
                                            No pending final approvals.
                                        </p>
                                    </div>
                                </TableEmpty>
                                <TableRow
                                    v-for="approval in finalApprovals.data"
                                    :key="approval.id"
                                >
                                    <TableCell class="whitespace-normal">
                                        <p class="font-medium">
                                            {{ approval.module }}
                                        </p>
                                        <p
                                            v-if="approval.justification"
                                            class="text-muted-foreground mt-1 max-w-xs text-xs"
                                        >
                                            {{ approval.justification }}
                                        </p>
                                    </TableCell>
                                    <TableCell>
                                        {{ approval.accessType }}
                                    </TableCell>
                                    <TableCell>{{
                                        approval.requester
                                    }}</TableCell>
                                    <TableCell>
                                        {{ approval.beneficiary }}
                                    </TableCell>
                                    <TableCell>
                                        <Badge
                                            :variant="
                                                approval.priority === 'High'
                                                    ? 'high'
                                                    : approval.priority ===
                                                        'Medium'
                                                      ? 'medium'
                                                      : 'low'
                                            "
                                            class="min-w-16"
                                        >
                                            {{ approval.priority }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        <Link
                                            :href="`/approvals/${approval.id}`"
                                            class="text-primary hover:text-primary/80 inline-flex items-center gap-1.5 text-sm font-semibold transition"
                                        >
                                            View
                                            <ArrowRight class="size-4" />
                                        </Link>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>

                    <nav
                        v-if="finalApprovals.last_page > 1"
                        class="flex flex-wrap justify-center gap-2 pt-2"
                        aria-label="Final approval pages"
                    >
                        <template
                            v-for="(link, index) in finalApprovals.links"
                            :key="index"
                        >
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
                </section>
            </TabsContent>
        </Tabs>
    </main>
</template>
