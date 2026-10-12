<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

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

    <main class="space-y-6 p-6">
        <div class="mb-4 flex items-center justify-between">
            <h1 class="text-2xl font-semibold">Users</h1>
            <Link
                href="/users/create"
                class="rounded bg-primary px-4 py-2 text-primary-foreground"
            >
                + Add User
            </Link>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-left">
                <thead>
                    <tr class="border-b">
                        <th class="p-2">Name</th>
                        <th class="p-2">Email</th>
                        <th class="p-2">Department</th>
                        <th class="p-2">Roles</th>
                        <th class="p-2">Status</th>
                        <th class="p-2">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="user in users.data" :key="user.id" class="border-b">
                        <td class="p-2">{{ user.fullname }}</td>
                        <td class="p-2">{{ user.email }}</td>
                        <td class="p-2">{{ user.department?.name ?? '—' }}</td>
                        <td class="p-2">{{ user.roles.map((role) => role.name).join(', ') }}</td>
                        <td class="p-2">{{ user.is_active ? 'Active' : 'Inactive' }}</td>
                        <td class="flex gap-3 p-2">
                            <Link :href="`/users/${user.id}`">View</Link>
                            <Link :href="`/users/${user.id}/edit`">Edit</Link>
                        </td>
                    </tr>
                    <tr v-if="users.data.length === 0">
                        <td class="p-4 text-center text-muted-foreground" colspan="6">
                            No users found.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

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
                    :class="{ 'bg-primary text-primary-foreground': link.active }"
                >
                    {{ paginationLabel(link.label) }}
                </Link>
                <span v-else class="rounded border px-3 py-1 text-muted-foreground">
                    {{ paginationLabel(link.label) }}
                </span>
            </template>
        </nav>
    </main>
</template>