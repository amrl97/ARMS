<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';

defineProps<{
    departments: {
        data: Array<{
            id: number;
            name: string;
            // users_count: number;
        }>;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
}>();

const page = usePage();

function remove(id: number) {
    if (confirm('Delete this department?')) {
        router.delete(`/departments/${id}`);
    }
}

function paginationLabel(label: string): string {
    return label.replace('&laquo; ', '').replace(' &raquo;', '');
}
</script>

<template>
    <Head title="Departments" />

    <div class="p-6">
        <div class="mb-4 flex items-center justify-between">
            <h1 class="text-2xl font-semibold">Departments</h1>
            <Link
                href="/departments/create"
                class="rounded bg-black px-4 py-2 text-white"
            >
                + Add Department
            </Link>
        </div>

        <p
            v-if="page.props.errors.department"
            class="mb-4 text-sm text-red-600"
        >
            {{ page.props.errors.department }}
        </p>

        <table class="w-full border text-left">
            <thead>
                <tr class="border-b bg-gray-50">
                    <th class="p-10">Name</th>
                    <!-- <th class="p-10">Number of User(s)</th> -->
                    <th class="p-10">Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="department in departments.data"
                    :key="department.id"
                    class="border-b"
                >
                    <td class="p-2">{{ department.name }}</td>
                    <!-- <td class="p-2">{{ department.users_count }}</td> -->
                    <td class="flex gap-3 p-2">
                        <Link :href="`/departments/${department.id}`"
                            >View</Link
                        >
                        <Link :href="`/departments/${department.id}/edit`"
                            >Edit</Link
                        >
                        <button
                            class="text-red-600"
                            @click="remove(department.id)"
                        >
                            Delete
                        </button>
                    </td>
                </tr>
                <tr v-if="departments.data.length === 0">
                    <td colspan="3" class="p-4 text-center text-gray-500">
                        No departments found.
                    </td>
                </tr>
            </tbody>
        </table>

        <nav
            v-if="departments.links.length > 3"
            class="mt-4 flex flex-wrap gap-2"
            aria-label="Pagination"
        >
            <template v-for="link in departments.links" :key="link.label">
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
