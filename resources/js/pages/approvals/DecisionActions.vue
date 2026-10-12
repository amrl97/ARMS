<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

const props = defineProps<{
    approvalId: number;
}>();

const form = useForm({
    decision: '',
    rejection_reason: '',
});

function decide(decision: 'approved' | 'rejected') {
    form.decision = decision;
    form.post(`/approvals/${props.approvalId}/decision`, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
        },
    });
}
</script>

<template>
    <div class="space-y-3 border-t pt-4">
        <label
            :for="`rejection-reason-${approvalId}`"
            class="block space-y-2 text-sm"
        >
            <span class="font-medium">Rejection reason</span>
            <textarea
                :id="`rejection-reason-${approvalId}`"
                v-model="form.rejection_reason"
                rows="2"
                maxlength="10000"
                class="w-full rounded-md border px-3 py-2"
                :aria-invalid="Boolean(form.errors.rejection_reason)"
                :disabled="form.processing"
            />
        </label>
        <p
            v-if="form.errors.rejection_reason"
            class="text-sm text-red-600"
            role="alert"
        >
            {{ form.errors.rejection_reason }}
        </p>
        <p v-if="form.errors.decision" class="text-sm text-red-600" role="alert">
            {{ form.errors.decision }}
        </p>
        <div class="flex flex-wrap gap-3">
            <button
                type="button"
                class="rounded-md border px-4 py-2 text-sm font-medium"
                :disabled="form.processing"
                @click="decide('approved')"
            >
                Approve
            </button>
            <button
                type="button"
                class="rounded-md border border-red-300 px-4 py-2 text-sm font-medium text-red-700"
                :disabled="form.processing"
                @click="decide('rejected')"
            >
                Reject
            </button>
        </div>
    </div>
</template>
