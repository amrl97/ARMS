<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps<{
    internalBeneficiaries: Array<{
        id: number;
        fullname: string;
        email: string;
    }>;
}>();

const form = useForm({
    internal_beneficiary: '',
});
</script>

<template>
    <Head title="New Access Request" />

    <div class="max-w-lg p-6">
        <h1 class="mb-4 text-2xl font-semibold">New Access Request</h1>

        <div class="space-y-2">
            <label for="internal_beneficiary" class="block">Internal beneficiary</label>
            <select
                id="internal_beneficiary"
                v-model="form.internal_beneficiary"
                class="w-full rounded border p-2"
            >
                <option value="">Myself</option>
                <option
                    v-for="beneficiary in internalBeneficiaries"
                    :key="beneficiary.id"
                    :value="beneficiary.id"
                >
                    {{ beneficiary.fullname }} ({{ beneficiary.email }})
                </option>
            </select>
        </div>

        <p class="mt-4 text-sm text-gray-600">
            Request details and submission will be available when the request workflow is implemented.
        </p>

        <Link href="/dashboard" class="mt-4 inline-block">Back to dashboard</Link>
    </div>
</template>