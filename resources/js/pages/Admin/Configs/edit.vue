<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import ApproverRules from './ApproverRules.vue';
import ModuleForm from './ModuleForm.vue';

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
    approverUsers: Array<{
        id: number;
        fullname: string;
        is_active: boolean;
        department: { name: string } | null;
    }>;
    approverRoles: Array<{ id: number; name: string }>;
    departments: Array<{ id: number; name: string }>;
}>();
</script>

<template>
    <Head :title="`Edit ${config.module_name}`" />
    <main class="space-y-8 p-6">
        <header class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold">Edit module: {{ config.module_name }}</h1>
            <Link :href="`/admin/configs/${config.id}`" class="underline">View module</Link>
        </header>

        <section class="space-y-4">
            <h2 class="text-xl font-semibold">Module settings</h2>
            <ModuleForm :config="config" />
        </section>

        <ApproverRules
            :config="config"
            :approver-users="approverUsers"
            :approver-roles="approverRoles"
            :departments="departments"
            editable
        />
    </main>
</template>
