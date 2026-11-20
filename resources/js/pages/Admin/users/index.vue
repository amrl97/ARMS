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
    users: {
        data: Array<{
            id: number;
            fullname: string;
            email: string;
            is_active: boolean;
            department: { name: string };
            roles: Array<{ name: string }>;
        }>;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
}>();

function paginationLabel(label: string): string {
    return label.replace('&laquo; ', '').replace(' &raquo;', '');
}
</script>

<template>
    <Head title="Users" />

    <main class="mx-auto w-full flex-1 space-y-8 p-4 sm:p-6 lg:p-8">
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-3xl font-semibold tracking-tight">Users</h1>
                <p class="text-muted-foreground mt-2 text-sm">
                    Manage user accounts, departments, and assigned roles.
                </p>
            </div>
            <Link
                href="/users/create"
                class="bg-primary text-primary-foreground hover:bg-primary/90 rounded-lg px-4 py-2 text-sm font-semibold transition"
            >
                + Add User
            </Link>
        </header>

        <section class="space-y-4" aria-labelledby="user-directory">
            <header class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2
                        id="user-directory"
                        class="text-xl font-semibold tracking-tight"
                    >
                        User directory
                    </h2>
                    <p class="text-muted-foreground mt-1.5 text-sm">
                        Review account details and access status.
                    </p>
                </div>
                <span
                    class="bg-muted text-muted-foreground rounded-full px-3 py-1 text-xs font-semibold"
                >
                    {{ users.data.length }} on this page
                </span>
            </header>

            <div class="bg-card overflow-hidden rounded-2xl border shadow-sm">
                <Table class="min-w-[900px]">
                    <TableHeader>
                        <TableRow>
                            <TableHead>Name</TableHead>
                            <TableHead>Email</TableHead>
                            <TableHead>Department</TableHead>
                            <TableHead>Roles</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead>Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="user in users.data" :key="user.id">
                            <TableCell class="font-medium">
                                {{ user.fullname }}
                            </TableCell>
                            <TableCell>{{ user.email }}</TableCell>
                            <TableCell>
                                {{ user.department?.name ?? '—' }}
                            </TableCell>
                            <TableCell class="whitespace-normal">
                                {{
                                    user.roles
                                        .map((role) => role.name)
                                        .join(', ')
                                }}
                            </TableCell>
                            <TableCell>
                                <Badge
                                    :variant="
                                        user.is_active ? 'default' : 'outline'
                                    "
                                >
                                    {{ user.is_active ? 'Active' : 'Inactive' }}
                                </Badge>
                            </TableCell>
                            <TableCell>
                                <div class="flex items-center gap-4">
                                    <Link
                                        :href="`/users/${user.id}`"
                                        class="text-primary hover:text-primary/80 text-sm font-semibold transition"
                                    >
                                        View
                                    </Link>
                                    <Link
                                        :href="`/users/${user.id}/edit`"
                                        class="text-primary hover:text-primary/80 text-sm font-semibold transition"
                                    >
                                        Edit
                                    </Link>
                                </div>
                            </TableCell>
                        </TableRow>
                        <TableEmpty
                            v-if="users.data.length === 0"
                            :colspan="6"
                            class="text-muted-foreground"
                        >
                            No users found.
                        </TableEmpty>
                    </TableBody>
                </Table>
            </div>
        </section>

        <nav
            v-if="users.links.length > 3"
            class="flex flex-wrap gap-2"
            aria-label="User pagination"
        >
            <template v-for="link in users.links" :key="link.label">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    class="rounded border px-3 py-1"
                    :class="{
                        'bg-primary text-primary-foreground': link.active,
                    }"
                >
                    {{ paginationLabel(link.label) }}
                </Link>
                <span
                    v-else
                    class="text-muted-foreground rounded border px-3 py-1"
                >
                    {{ paginationLabel(link.label) }}
                </span>
            </template>
        </nav>
    </main>
</template>
