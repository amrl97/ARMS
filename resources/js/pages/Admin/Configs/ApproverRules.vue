<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

type ApproverUser = {
    id?: number;
    fullname: string;
    is_active?: boolean;
    department: { name: string } | null;
};

type ApproverRole = { id?: number; name: string };
type Department = { id?: number; name: string };
type Rule = {
    id: number;
    user_id: number | null;
    role_id: number | null;
    department_id: number | null;
    resolved_user_count: number;
    user: ApproverUser | null;
    role: ApproverRole | null;
    department: Department | null;
};

const props = defineProps<{
    config: {
        id: number;
        module_name: string;
        max_approvers: number;
        required_approvals: number;
        module_approvers: Rule[];
    };
    editable: boolean;
    approverUsers?: ApproverUser[];
    approverRoles?: ApproverRole[];
    departments?: Department[];
}>();

const isAdding = ref(false);
const ruleType = ref<'user' | 'role'>('role');
const form = useForm<{
    module_id: number;
    user_id: number | null;
    role_id: number | null;
    department_id: number | null;
}>({
    module_id: props.config.id,
    user_id: null,
    role_id: null,
    department_id: null,
});

function addRule(): void {
    form.user_id = ruleType.value === 'user' ? form.user_id : null;
    form.role_id = ruleType.value === 'role' ? form.role_id : null;
    form.post('/admin/configs/approvers', {
        onSuccess: () => {
            isAdding.value = false;
            form.reset();
            form.module_id = props.config.id;
        },
    });
}

function removeRule(rule: Rule): void {
    if (confirm('Remove this module approver rule?')) {
        router.delete(`/admin/configs/approvers/${rule.id}`);
    }
}

function ruleName(rule: Rule): string {
    if (rule.user) {
        return `${rule.user.fullname}${rule.user.department ? ` (${rule.user.department.name})` : ''}`;
    }

    return `${rule.role?.name ?? 'Unknown role'}${rule.department ? ` - ${rule.department.name}` : ' - All departments'}`;
}
</script>

<template>
    <section class="space-y-4">
        <header class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-xl font-semibold">Approver rules</h2>
            <button
                v-if="editable"
                class="rounded border px-3 py-2 disabled:cursor-not-allowed disabled:opacity-50"
                type="button"
                :disabled="config.module_approvers.length >= config.max_approvers"
                @click="isAdding = !isAdding"
            >
                Add approver rule
            </button>
        </header>

        <p
            v-if="config.module_approvers.length === 0 && config.max_approvers > 0"
            class="rounded border border-amber-500 p-3 text-sm text-amber-800"
            role="status"
        >
            No approver rules are configured. This module's approval step currently resolves to nobody.
        </p>

        <ul v-if="config.module_approvers.length" class="space-y-2">
            <li
                v-for="rule in config.module_approvers"
                :key="rule.id"
                class="flex flex-wrap items-center justify-between gap-3 rounded border p-3"
            >
                <div>
                    <p class="font-medium">{{ ruleName(rule) }}</p>
                    <p
                        v-if="rule.resolved_user_count === 0"
                        class="text-sm text-amber-800"
                        role="status"
                    >
                        Warning: this rule currently resolves to nobody.
                    </p>
                    <p v-else class="text-sm text-muted-foreground">
                        Resolves to {{ rule.resolved_user_count }}
                        active eligible user{{ rule.resolved_user_count === 1 ? '' : 's' }}.
                    </p>
                </div>
                <button
                    v-if="editable"
                    class="text-sm text-red-600 underline"
                    type="button"
                    @click="removeRule(rule)"
                >
                    Remove
                </button>
            </li>
        </ul>

        <form v-if="editable && isAdding" class="max-w-xl space-y-3 rounded border p-4" @submit.prevent="addRule">
            <div class="space-y-1">
                <label for="approver-type" class="block font-medium">Approver rule type</label>
                <select
                    id="approver-type"
                    v-model="ruleType"
                    class="w-full rounded border px-3 py-2"
                    @change="form.user_id = null; form.role_id = null; form.department_id = null"
                >
                    <option value="role">Role</option>
                    <option value="user">Specific user</option>
                </select>
            </div>

            <div v-if="ruleType === 'user'" class="space-y-1">
                <label for="approver-user" class="block font-medium">User</label>
                <select id="approver-user" v-model.number="form.user_id" class="w-full rounded border px-3 py-2" required>
                    <option :value="null" disabled>Select a user</option>
                    <option v-for="user in approverUsers" :key="user.id" :value="user.id">
                        {{ user.fullname }}{{ user.department ? ` - ${user.department.name}` : '' }}{{ user.is_active ? '' : ' (Inactive)' }}
                    </option>
                </select>
                <p v-if="form.errors.user_id" class="text-sm text-red-600">{{ form.errors.user_id }}</p>
            </div>

            <template v-else>
                <div class="space-y-1">
                    <label for="approver-role" class="block font-medium">Role</label>
                    <select id="approver-role" v-model.number="form.role_id" class="w-full rounded border px-3 py-2" required>
                        <option :value="null" disabled>Select a role</option>
                        <option v-for="role in approverRoles" :key="role.id" :value="role.id">{{ role.name }}</option>
                    </select>
                    <p v-if="form.errors.role_id" class="text-sm text-red-600">{{ form.errors.role_id }}</p>
                </div>
                <div class="space-y-1">
                    <label for="approver-department" class="block font-medium">Department (optional)</label>
                    <select id="approver-department" v-model.number="form.department_id" class="w-full rounded border px-3 py-2">
                        <option :value="null">All departments</option>
                        <option v-for="department in departments" :key="department.id" :value="department.id">
                            {{ department.name }}
                        </option>
                    </select>
                    <p v-if="form.errors.department_id" class="text-sm text-red-600">{{ form.errors.department_id }}</p>
                </div>
            </template>

            <p v-if="form.errors.module_id" class="text-sm text-red-600">{{ form.errors.module_id }}</p>
            <div class="flex gap-3">
                <button
                    class="rounded bg-primary px-4 py-2 text-primary-foreground disabled:opacity-50"
                    type="submit"
                    :disabled="form.processing"
                >
                    Add rule
                </button>
                <button class="rounded border px-4 py-2" type="button" @click="isAdding = false">Cancel</button>
            </div>
        </form>
    </section>
</template>
