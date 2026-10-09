<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    configs: {
        data: Array<{
            id: number;
            module_name: string;
            max_approvers: number;
            required_approvals: number;
            is_active: boolean;
            module_approvers_count: number;
        }>;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
}>();

function paginationLabel(label: string): string {
    return label.replace('&laquo; ', '').replace(' &raquo;', '');
}
</script>

<template>
    <Head title="Module Configuration" />

    <main class="space-y-6 p-6">
        <header class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold">Module Configuration</h1>
            <Link
                href="/admin/configs/create"
                class="rounded bg-primary px-4 py-2 text-primary-foreground"
            >
                Create module
            </Link>
        </header>

        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-left">
                <thead>
                    <tr class="border-b">
                        <th class="p-2">Module</th>
                        <th class="p-2">Approver rows</th>
                        <th class="p-2">Required approvals</th>
                        <th class="p-2">Status</th>
                        <th class="p-2">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="config in configs.data" :key="config.id" class="border-b">
                        <td class="p-2">{{ config.module_name }}</td>
                        <td class="p-2">
                            {{ config.module_approvers_count }} / {{ config.max_approvers }}
                        </td>
                        <td class="p-2">{{ config.required_approvals }}</td>
                        <td class="p-2">{{ config.is_active ? 'Active' : 'Inactive' }}</td>
                        <td class="flex gap-3 p-2">
                            <Link :href="`/admin/configs/${config.id}`">View</Link>
                            <Link :href="`/admin/configs/${config.id}/edit`">Edit</Link>
                        </td>
                    </tr>
                    <tr v-if="configs.data.length === 0">
                        <td class="p-4 text-center text-gray-500" colspan="5">
                            No modules configured yet.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <nav
            v-if="configs.links.length > 3"
            class="flex flex-wrap gap-2"
            aria-label="Module configuration pagination"
        >
            <template v-for="link in configs.links" :key="link.label">
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
    </main>
</template>
