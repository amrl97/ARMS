<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps<{
    user: {
        id: number; fullname: string; email: string; is_active: number;
        created_at: string; updated_at: string;
        department: { name: string };
        roles: Array<{ name: string }>;
    };
}>();
</script>

<template>
    <Head title="User Details" />
    <div class="p-6">
        <h1 class="mb-4 text-2xl font-semibold">User Details</h1>
        <dl class="space-y-2">
            <div>
                <dt class="font-medium">Full Name</dt>
                <dd>{{ user.fullname }}</dd>
            </div>
            <div>
                <dt class="font-medium">Email</dt>
                <dd>{{ user.email }}</dd>
            </div>
            <div>
                <dt class="font-medium">Department</dt>
                <dd>{{ user.department.name }}</dd>
            </div>
            <div>
                <dt class="font-medium">Roles</dt>
                <dd>{{ user.roles.map((role) => role.name).join(', ') }}</dd>
            </div>
            <div>
                <dt class="font-medium">Status</dt>
                <dd>{{ user.is_active ? 'Active' : 'Inactive' }}</dd>
            </div>
            <div>
                <dt class="font-medium">Created At</dt>
                <dd>{{ user.created_at }}</dd>
            </div>
        </dl>
        <div class="mt-4 flex gap-3">
            <Link :href="`/users/${user.id}/edit`">Edit</Link>
            <Link href="/users">Back to list</Link>
        </div>
    </div>
</template>