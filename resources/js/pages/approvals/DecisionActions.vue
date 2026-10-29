<script setup lang="ts">
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Check, X } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';

const props = defineProps<{
    approvalId: number;
}>();

const approveDialogOpen = ref(false);
const rejectDialogOpen = ref(false);

const form = useForm({
    decision: '',
    rejection_reason: '',
});

function decide(decision: 'approved' | 'rejected'): void {
    form.decision = decision;
    form.post(`/approvals/${props.approvalId}/decision`, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            approveDialogOpen.value = false;
            rejectDialogOpen.value = false;
        },
    });
}

function openRejectDialog(): void {
    form.clearErrors();
    form.rejection_reason = '';
    rejectDialogOpen.value = true;
}
</script>

<template>
    <div class="flex flex-wrap gap-3">
        <Button
            type="button"
            :disabled="form.processing"
            class="h-10 min-w-32 rounded-xl bg-emerald-600 px-5 text-white shadow-sm transition hover:bg-emerald-700"
            @click="approveDialogOpen = true"
        >
            <Check class="size-4" />
            Approve
        </Button>
        <Button
            type="button"
            variant="outline"
            :disabled="form.processing"
            class="border-destructive/30 bg-background text-destructive hover:bg-destructive h-10 min-w-32 rounded-xl px-5 shadow-sm transition hover:text-white"
            @click="openRejectDialog"
        >
            <X class="size-4" />
            Reject
        </Button>
    </div>

    <AlertDialog v-model:open="approveDialogOpen">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle
                    >Approve this access request?</AlertDialogTitle
                >
                <AlertDialogDescription>
                    This will approve the access request at this stage.
                </AlertDialogDescription>
            </AlertDialogHeader>

            <p
                v-if="form.errors.decision"
                class="text-destructive text-sm"
                role="alert"
            >
                {{ form.errors.decision }}
            </p>

            <AlertDialogFooter>
                <AlertDialogCancel :disabled="form.processing">
                    Cancel
                </AlertDialogCancel>
                <Button
                    type="button"
                    :disabled="form.processing"
                    @click="decide('approved')"
                >
                    {{ form.processing ? 'Approving...' : 'Confirm approval' }}
                </Button>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>

    <AlertDialog v-model:open="rejectDialogOpen">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>Reject this access request?</AlertDialogTitle>
                <AlertDialogDescription>
                    Enter a reason for rejecting this request. The requester
                    will be able to see it.
                </AlertDialogDescription>
            </AlertDialogHeader>

            <label
                :for="`rejection-reason-${approvalId}`"
                class="grid gap-2 text-sm font-medium"
            >
                Rejection reason
                <textarea
                    :id="`rejection-reason-${approvalId}`"
                    v-model="form.rejection_reason"
                    rows="4"
                    maxlength="10000"
                    required
                    class="border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive w-full rounded-md border bg-transparent px-3 py-2 text-base shadow-xs outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                    :aria-invalid="Boolean(form.errors.rejection_reason)"
                    :aria-describedby="
                        form.errors.rejection_reason
                            ? `rejection-reason-error-${approvalId}`
                            : undefined
                    "
                    :disabled="form.processing"
                />
            </label>
            <p
                v-if="form.errors.rejection_reason"
                :id="`rejection-reason-error-${approvalId}`"
                class="text-destructive text-sm"
                role="alert"
            >
                {{ form.errors.rejection_reason }}
            </p>
            <p
                v-if="form.errors.decision"
                class="text-destructive text-sm"
                role="alert"
            >
                {{ form.errors.decision }}
            </p>

            <AlertDialogFooter>
                <AlertDialogCancel :disabled="form.processing">
                    Cancel
                </AlertDialogCancel>
                <Button
                    type="button"
                    variant="destructive"
                    :disabled="
                        form.processing || form.rejection_reason.trim() === ''
                    "
                    @click="decide('rejected')"
                >
                    {{ form.processing ? 'Rejecting...' : 'Confirm rejection' }}
                </Button>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
