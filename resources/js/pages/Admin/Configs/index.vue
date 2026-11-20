<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Table,
    TableBody,
    TableCell,
    TableEmpty,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';

defineProps<{
    configs: {
        data: Array<{
            id: number;
            module_name: string;
            max_approvers: number;
            required_approvals: number;
            is_active: boolean;
            module_approvers_count: number;
        }>;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
}>();

function paginationLabel(label: string): string {
    return label.replace('&laquo; ', '').replace(' &raquo;', '');
}
</script>

<template>
    <Head title="Module Configuration" />

    <main class="mx-auto w-full flex-1 space-y-8 p-4 sm:p-6 lg:p-8">
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-3xl font-semibold tracking-tight">
                    Module Configuration
                </h1>
                <p class="text-muted-foreground mt-2 text-sm">
                    Manage modules and their approval requirements.
                </p>
            </div>
            <Link
                href="/admin/configs/create"
                class="bg-primary text-primary-foreground hover:bg-primary/90 rounded-lg px-4 py-2 text-sm font-semibold transition"
            >
                Create module
            </Link>
        </header>

        <section class="space-y-4" aria-labelledby="module-configurations">
            <header class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2
                        id="module-configurations"
                        class="text-xl font-semibold tracking-tight"
                    >
                        Configured modules
                    </h2>
                    <p class="text-muted-foreground mt-1.5 text-sm">
                        View each module’s approver capacity and status.
                    </p>
                </div>
                <span
                    class="bg-muted text-muted-foreground rounded-full px-3 py-1 text-xs font-semibold"
                >
                    {{ configs.data.length }} on this page
                </span>
            </header>

            <div class="bg-card overflow-hidden rounded-2xl border shadow-sm">
                <Table class="min-w-[700px]">
                    <TableHeader>
                        <TableRow>
                            <TableHead>Module</TableHead>
                            <TableHead>Approver rows</TableHead>
                            <TableHead>Required approvals</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead>Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="config in configs.data"
                            :key="config.id"
                        >
                            <TableCell class="font-medium">
                                {{ config.module_name }}
                            </TableCell>
                            <TableCell>
                                {{ config.module_approvers_count }} /
                                {{ config.max_approvers }}
                            </TableCell>
                            <TableCell>
                                {{ config.required_approvals }}
                            </TableCell>
                            <TableCell>
                                <Badge
                                    :variant="
                                        config.is_active ? 'default' : 'outline'
                                    "
                                >
                                    {{
                                        config.is_active ? 'Active' : 'Inactive'
                                    }}
                                </Badge>
                            </TableCell>
                            <TableCell>
                                <div class="flex items-center gap-4">
                                    <Link
                                        :href="`/admin/configs/${config.id}`"
                                        class="text-primary hover:text-primary/80 text-sm font-semibold transition"
                                    >
                                        View
                                    </Link>
                                    <Link
                                        :href="`/admin/configs/${config.id}/edit`"
                                        class="text-primary hover:text-primary/80 text-sm font-semibold transition"
                                    >
                                        Edit
                                    </Link>
                                </div>
                            </TableCell>
                        </TableRow>
                        <TableEmpty
                            v-if="configs.data.length === 0"
                            :colspan="5"
                            class="text-muted-foreground"
                        >
                            No modules configured yet.
                        </TableEmpty>
                    </TableBody>
                </Table>
            </div>
        </section>

        <nav
            v-if="configs.links.length > 3"
            class="flex flex-wrap gap-2"
            aria-label="Module configuration pagination"
        >
            <template v-for="link in configs.links" :key="link.label">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    class="rounded border px-3 py-1"
                    :class="{ 'bg-black text-white': link.active }"
                >
                    {{ paginationLabel(link.label) }}
                </Link>
                <span v-else class="rounded border px-3 py-1 text-gray-400">
                    {{ paginationLabel(link.label) }}
                </span>
            </template>
        </nav>
    </main>
</template>
