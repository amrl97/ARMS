<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    Table,
    TableBody,
    TableCell,
    TableEmpty,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

defineProps<{
    departments: {
        data: Array<{
            id: number;
            name: string;
            users_count: number;
        }>;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
}>();

const page = usePage();

function remove(id: number) {
    if (confirm('Delete this department?')) {
        router.delete(`/departments/${id}`);
    }
}

function paginationLabel(label: string): string {
    return label.replace('&laquo; ', '').replace(' &raquo;', '');
}
</script>

<template>
    <Head title="Departments" />

    <main class="mx-auto w-full flex-1 space-y-8 p-4 sm:p-6 lg:p-8">
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-3xl font-semibold tracking-tight">
                    Departments
                </h1>
                <p class="text-muted-foreground mt-2 text-sm">
                    Organize users by department and manage department records.
                </p>
            </div>
            <Link
                href="/departments/create"
                class="bg-primary text-primary-foreground hover:bg-primary/90 rounded-lg px-4 py-2 text-sm font-semibold transition"
            >
                + Add Department
            </Link>
        </header>

        <p
            v-if="page.props.errors.department"
            class="mb-4 text-sm text-red-600"
        >
            {{ page.props.errors.department }}
        </p>

        <section class="space-y-4" aria-labelledby="department-directory">
            <header class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2
                        id="department-directory"
                        class="text-xl font-semibold tracking-tight"
                    >
                        Department directory
                    </h2>
                    <p class="text-muted-foreground mt-1.5 text-sm">
                        View departments and their current user counts.
                    </p>
                </div>
                <span
                    class="bg-muted text-muted-foreground rounded-full px-3 py-1 text-xs font-semibold"
                >
                    {{ departments.data.length }} on this page
                </span>
            </header>

            <div class="bg-card overflow-hidden rounded-2xl border shadow-sm">
                <Table class="min-w-[600px]">
                    <TableHeader>
                        <TableRow>
                            <TableHead>Name</TableHead>
                            <TableHead>Users</TableHead>
                            <TableHead>Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="department in departments.data"
                            :key="department.id"
                        >
                            <TableCell class="font-medium">
                                {{ department.name }}
                            </TableCell>
                            <TableCell>{{ department.users_count }}</TableCell>
                            <TableCell>
                                <div class="flex items-center gap-4">
                                    <Link
                                        :href="`/departments/${department.id}`"
                                        class="text-primary hover:text-primary/80 text-sm font-semibold transition"
                                    >
                                        View
                                    </Link>
                                    <Link
                                        :href="`/departments/${department.id}/edit`"
                                        class="text-primary hover:text-primary/80 text-sm font-semibold transition"
                                    >
                                        Edit
                                    </Link>
                                    <button
                                        class="text-destructive hover:text-destructive/80 text-sm font-semibold transition"
                                        @click="remove(department.id)"
                                    >
                                        Delete
                                    </button>
                                </div>
                            </TableCell>
                        </TableRow>
                        <TableEmpty
                            v-if="departments.data.length === 0"
                            :colspan="3"
                            class="text-muted-foreground"
                        >
                            No departments found.
                        </TableEmpty>
                    </TableBody>
                </Table>
            </div>
        </section>

        <nav
            v-if="departments.links.length > 3"
            class="mt-4 flex flex-wrap gap-2"
            aria-label="Pagination"
        >
            <template v-for="link in departments.links" :key="link.label">
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
