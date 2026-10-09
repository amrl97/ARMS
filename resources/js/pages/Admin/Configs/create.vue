<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ModuleForm from './ModuleForm.vue';

defineProps<{
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
    <Head title="Create Module" />
    <main class="space-y-6 p-6">
        <h1 class="text-2xl font-semibold">Create module</h1>
        <p class="max-w-2xl text-sm text-muted-foreground">
            Configure module settings and approver rules together. You can adjust rules later from the module edit page.
        </p>
        <ModuleForm
            :approver-users="approverUsers"
            :approver-roles="approverRoles"
            :departments="departments"
        />
    </main>
</template>
