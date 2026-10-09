<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    department: {
        id: number;
        name: string;
        users_count: number;
        created_at: string;
        updated_at: string;
    };
}>();
</script>

<template>
    <Head title="Department Details" />

    <div class="p-6">
        <h1 class="mb-4 text-2xl font-semibold">Department Details</h1>
        <dl class="space-y-2">
            <div>
                <dt class="font-medium">Name</dt>
                <dd>{{ department.name }}</dd>
            </div>
            <div>
                <dt class="font-medium">Users</dt>
                <dd>{{ department.users_count }}</dd>
            </div>
            <div>
                <dt class="font-medium">Created At</dt>
                <dd>{{ department.created_at }}</dd>
            </div>
            <div>
                <dt class="font-medium">Updated At</dt>
                <dd>{{ department.updated_at }}</dd>
            </div>
        </dl>
        <div class="mt-4 flex gap-3">
            <Link :href="`/departments/${department.id}/edit`">Edit</Link>
            <Link href="/departments">Back to list</Link>
        </div>
    </div>
</template>
