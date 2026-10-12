<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';

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

    <div class="p-6">
        <div class="mb-4 flex items-center justify-between">
            <h1 class="text-2xl font-semibold">Roles</h1>
            <Link
                href="/roles/create"
                class="rounded bg-black px-4 py-2 text-white"
            >
                + Add Role
            </Link>
        </div>

        <p v-if="page.props.errors.role" class="mb-4 text-sm text-red-600">
            {{ page.props.errors.role }}
        </p>

        <table class="w-full border text-left">
            <thead>
                <tr class="border-b bg-gray-50">
                    <th class="p-2">Name</th>
                    <th class="p-2">Users</th>
                    <th class="p-2">Module approver rules</th>
                    <th class="p-2">Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="role in roles.data" :key="role.id" class="border-b">
                    <td class="p-2">{{ role.name }}</td>
                    <td class="p-2">{{ role.users_count }}</td>
                    <td class="p-2">{{ role.module_approvers_count }}</td>
                    <td class="flex gap-3 p-2">
                        <Link :href="`/roles/${role.id}`">View</Link>
                        <Link :href="`/roles/${role.id}/edit`">Edit</Link>
                        <button class="text-red-600" @click="remove(role.id)">
                            Delete
                        </button>
                    </td>
                </tr>
                <tr v-if="roles.data.length === 0">
                    <td colspan="4" class="p-4 text-center text-muted-foreground">
                        No roles found.
                    </td>
                </tr>
            </tbody>
        </table>

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
    </div>
</template>
