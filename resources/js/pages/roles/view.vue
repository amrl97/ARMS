<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    role: {
        id: number;
        name: string;
        users_count: number;
        module_approvers_count: number;
        created_at: string;
        updated_at: string;
    };
}>();
</script>

<template>
    <Head title="Role Details" />

    <div class="p-6">
        <h1 class="mb-4 text-2xl font-semibold">Role Details</h1>
        <dl class="space-y-2">
            <div>
                <dt class="font-medium">Name</dt>
                <dd>{{ role.name }}</dd>
            </div>

            <div>
                <dt class="font-medium">Users</dt>
                <dd>{{ role.users_count }}</dd>
            </div>
            <div>
                <dt class="font-medium">Module approver rules</dt>
                <dd>{{ role.module_approvers_count }}</dd>
            </div>

            <div>
                <dt class="font-medium">Created At</dt>
                <dd>{{ role.created_at }}</dd>
            </div>
            <div>
                <dt class="font-medium">Updated At</dt>
                <dd>{{ role.updated_at }}</dd>
            </div>
        </dl>
        <div class="mt-4 flex gap-3">
            <Link :href="`/roles/${role.id}/edit`">Edit</Link>
            <Link href="/roles">Back to list</Link>
        </div>
    </div>
</template>
