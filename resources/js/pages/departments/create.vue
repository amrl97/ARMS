<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
});

function submit() {
    form.post('/departments');
}
</script>

<template>
    <Head title="Add Department" />

    <div class="max-w-lg p-6">
        <h1 class="mb-4 text-2xl font-semibold">Add Department</h1>

        <form class="space-y-4" @submit.prevent="submit">
            <div>
                <label for="name" class="block">Name</label>
                <input
                    id="name"
                    v-model="form.name"
                    type="text"
                    class="w-full rounded border p-2"
                />
                <p v-if="form.errors.name" class="text-sm text-red-600">
                    {{ form.errors.name }}
                </p>
            </div>

            <div class="flex gap-3">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="rounded bg-black px-4 py-2 text-white"
                >
                    Save
                </button>
                <Link href="/departments" class="px-4 py-2">Cancel</Link>
            </div>
        </form>
    </div>
</template>
