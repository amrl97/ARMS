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
    roles: {
        data: Array<{
            id: number;
            name: string;
            users_count: number;
            module_approvers_count: number;
        }>;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
}>();

const page = usePage();

function remove(id: number) {
    if (confirm('Delete this role?')) {
        router.delete(`/roles/${id}`);
    }
}

function paginationLabel(label: string): string {
    return label.replace('&laquo; ', '').replace(' &raquo;', '');
}
</script>

<template>
    <Head title="Roles" />

    <main class="mx-auto w-full flex-1 space-y-8 p-4 sm:p-6 lg:p-8">
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-3xl font-semibold tracking-tight">Roles</h1>
                <p class="text-muted-foreground mt-2 text-sm">
                    Manage roles and the access rules assigned to each role.
                </p>
            </div>
            <Link
                href="/roles/create"
                class="bg-primary text-primary-foreground hover:bg-primary/90 rounded-lg px-4 py-2 text-sm font-semibold transition"
            >
                + Add Role
            </Link>
        </header>

        <p v-if="page.props.errors.role" class="mb-4 text-sm text-red-600">
            {{ page.props.errors.role }}
        </p>

        <section class="space-y-4" aria-labelledby="role-directory">
            <header class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2
                        id="role-directory"
                        class="text-xl font-semibold tracking-tight"
                    >
                        Role directory
                    </h2>
                    <p class="text-muted-foreground mt-1.5 text-sm">
                        Review role assignments and module approver rules.
                    </p>
                </div>
                <span
                    class="bg-muted text-muted-foreground rounded-full px-3 py-1 text-xs font-semibold"
                >
                    {{ roles.data.length }} on this page
                </span>
            </header>

            <div class="bg-card overflow-hidden rounded-2xl border shadow-sm">
                <Table class="min-w-[700px]">
                    <TableHeader>
                        <TableRow>
                            <TableHead>Name</TableHead>
                            <TableHead>Users</TableHead>
                            <TableHead>Module approver rules</TableHead>
                            <TableHead>Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="role in roles.data" :key="role.id">
                            <TableCell class="font-medium">
                                {{ role.name }}
                            </TableCell>
                            <TableCell>{{ role.users_count }}</TableCell>
                            <TableCell>
                                {{ role.module_approvers_count }}
                            </TableCell>
                            <TableCell>
                                <div class="flex items-center gap-4">
                                    <Link
                                        :href="`/roles/${role.id}`"
                                        class="text-primary hover:text-primary/80 text-sm font-semibold transition"
                                    >
                                        View
                                    </Link>
                                    <Link
                                        :href="`/roles/${role.id}/edit`"
                                        class="text-primary hover:text-primary/80 text-sm font-semibold transition"
                                    >
                                        Edit
                                    </Link>
                                    <button
                                        class="text-destructive hover:text-destructive/80 text-sm font-semibold transition"
                                        @click="remove(role.id)"
                                    >
                                        Delete
                                    </button>
                                </div>
                            </TableCell>
                        </TableRow>
                        <TableEmpty
                            v-if="roles.data.length === 0"
                            :colspan="4"
                            class="text-muted-foreground"
                        >
                            No roles found.
                        </TableEmpty>
                    </TableBody>
                </Table>
            </div>
        </section>

        <nav
            v-if="roles.links.length > 3"
            class="mt-4 flex flex-wrap gap-2"
            aria-label="Pagination"
        >
            <template v-for="link in roles.links" :key="link.label">
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
