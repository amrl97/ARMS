<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';

defineProps<{
    users: {
        data: Array<{
            id: number;
            fullname: string;
            email: string;
            is_active: number;
            department: { name: string };
            roles: Array<{ name: string }>;
        }>;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
}>();

function remove(id: number) {
    if (confirm('Delete this user?')) {
        router.delete(`/users/${id}`);
    }
}
</script>

<template>
    <Head title="Users" />

    <div class="p-6">
        <div class="mb-4 flex items-center justify-between">
            <h1 class="text-2xl font-semibold">Users</h1>
            <Link href="/users/create" class="rounded bg-black px-4 py-2 text-white">
                + Add User
            </Link>
        </div>

        <table class="w-full border text-left">
            <thead>
                <tr class="border-b bg-gray-50">
                    <th class="p-2">Name</th>
                    <th class="p-2">Email</th>
                    <th class="p-2">Department</th>
                    <th class="p-2">Roles</th>
                    <th class="p-2">Active</th>
                    <th class="p-2">Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="user in users.data" :key="user.id" class="border-b">
                    <td class="p-2">{{ user.fullname }}</td>
                    <td class="p-2">{{ user.email }}</td>
                    <td class="p-2">{{ user.department.name }}</td>
                    <td class="p-2">{{ user.roles.map((role) => role.name).join(', ') }}</td>
                    <td class="p-2">{{ user.is_active ? 'Yes' : 'No' }}</td>
                    <td class="flex gap-3 p-2">
                        <Link :href="`/users/${user.id}`">View</Link>
                        <Link :href="`/users/${user.id}/edit`">Edit</Link>
                        <button class="text-red-600" @click="remove(user.id)">Delete</button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>