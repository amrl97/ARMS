<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import ApproverRules from './ApproverRules.vue';

defineProps<{
    config: {
        id: number;
        module_name: string;
        max_approvers: number;
        required_approvals: number;
        is_active: boolean;
        module_approvers: Array<{
            id: number;
            user_id: number | null;
            role_id: number | null;
            department_id: number | null;
            resolved_user_count: number;
            user: { fullname: string; department: { name: string } | null } | null;
            role: { name: string } | null;
            department: { name: string } | null;
        }>;
    };
}>();
</script>

<template>
    <Head :title="config.module_name" />
    <main class="space-y-8 p-6">
        <header class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold">{{ config.module_name }}</h1>
            <div class="flex gap-4">
                <Link href="/admin/configs" class="underline">All modules</Link>
                <Link :href="`/admin/configs/${config.id}/edit`" class="underline">Edit</Link>
            </div>
        </header>

        <dl class="grid max-w-2xl gap-4 sm:grid-cols-2">
            <div>
                <dt class="font-medium">Status</dt>
                <dd>{{ config.is_active ? 'Active' : 'Inactive' }}</dd>
            </div>
            <div>
                <dt class="font-medium">Maximum approver rows</dt>
                <dd>{{ config.max_approvers }}</dd>
            </div>
            <div>
                <dt class="font-medium">Required approvals</dt>
                <dd>{{ config.required_approvals }}</dd>
            </div>
        </dl>

        <ApproverRules :config="config" :editable="false" />
    </main>
</template>
