<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';

type ModuleConfig = {
    id: number;
    module_name: string;
    max_approvers: number;
    required_approvals: number;
    is_active: boolean;
};

type ApproverUser = {
    id: number;
    fullname: string;
    is_active: boolean;
    department: { name: string } | null;
};

type ApproverRole = { id: number; name: string };
type Department = { id: number; name: string };
type DraftApprover = {
    user_id: number | null;
    role_id: number | null;
    department_id: number | null;
    type: 'user' | 'role';
};

const props = defineProps<{
    config?: ModuleConfig;
    approverUsers?: ApproverUser[];
    approverRoles?: ApproverRole[];
    departments?: Department[];
}>();

const form = useForm({
    module_name: props.config?.module_name ?? '',
    max_approvers: props.config?.max_approvers ?? 0,
    required_approvals: props.config?.required_approvals ?? 0,
    is_active: props.config?.is_active ?? true,
    approvers: [] as DraftApprover[],
});

function setMaxApprovers(): void {
    form.required_approvals = form.max_approvers === 0 ? 0 : 1;
}

function addApprover(): void {
    form.approvers.push({
        user_id: null,
        role_id: null,
        department_id: null,
        type: 'role',
    });
}

function removeApprover(index: number): void {
    form.approvers.splice(index, 1);
}

function submit(): void {
    if (props.config) {
        form.put(`/admin/configs/${props.config.id}`);
    } else {
        form.post('/admin/configs');
    }
}
</script>

<template>
    <form class="max-w-2xl space-y-4" @submit.prevent="submit">
        <div class="space-y-1">
            <label for="module-name" class="block font-medium">Module name</label>
            <input
                id="module-name"
                v-model="form.module_name"
                class="w-full rounded border px-3 py-2"
                type="text"
                maxlength="255"
                required
            />
            <p v-if="form.errors.module_name" class="text-sm text-red-600">
                {{ form.errors.module_name }}
            </p>
        </div>

        <div class="space-y-1">
            <label for="max-approvers" class="block font-medium">Maximum approver rows</label>
            <input
                id="max-approvers"
                v-model.number="form.max_approvers"
                class="w-full rounded border px-3 py-2"
                type="number"
                min="0"
                max="255"
                required
                @change="setMaxApprovers"
            />
            <p v-if="form.errors.max_approvers" class="text-sm text-red-600">
                {{ form.errors.max_approvers }}
            </p>
        </div>

        <div class="space-y-1">
            <label for="required-approvals" class="block font-medium">Required approvals</label>
            <input
                id="required-approvals"
                v-model.number="form.required_approvals"
                class="w-full rounded border px-3 py-2"
                type="number"
                min="0"
                :max="form.max_approvers > 0 ? 1 : 0"
                required
            />
            <p v-if="form.errors.required_approvals" class="text-sm text-red-600">
                {{ form.errors.required_approvals }}
            </p>
        </div>

        <label class="flex items-center gap-2">
            <input v-model="form.is_active" type="checkbox" />
            <span>Module is active</span>
        </label>
        <p v-if="form.errors.is_active" class="text-sm text-red-600">
            {{ form.errors.is_active }}
        </p>

        <fieldset v-if="!config" class="space-y-4 rounded border p-4">
            <legend class="px-2 font-semibold">Approver rules</legend>
            <p class="text-sm text-muted-foreground">
                Add approvers now, or leave this empty and configure rules after the module is created.
            </p>
            <p v-if="form.errors.approvers" class="text-sm text-red-600">
                {{ form.errors.approvers }}
            </p>
            <div
                v-for="(approver, index) in form.approvers"
                :key="index"
                class="space-y-3 rounded border p-3"
            >
                <div class="space-y-1">
                    <label :for="`new-approver-type-${index}`" class="block font-medium">Rule type</label>
                    <select
                        :id="`new-approver-type-${index}`"
                        v-model="approver.type"
                        class="w-full rounded border px-3 py-2"
                        @change="approver.user_id = null; approver.role_id = null; approver.department_id = null"
                    >
                        <option value="role">Role</option>
                        <option value="user">Specific user</option>
                    </select>
                </div>
                <div v-if="approver.type === 'user'" class="space-y-1">
                    <label :for="`new-approver-user-${index}`" class="block font-medium">User</label>
                    <select
                        :id="`new-approver-user-${index}`"
                        v-model.number="approver.user_id"
                        class="w-full rounded border px-3 py-2"
                        required
                    >
                        <option :value="null" disabled>Select a user</option>
                        <option v-for="user in approverUsers" :key="user.id" :value="user.id">
                            {{ user.fullname }}{{ user.department ? ` - ${user.department.name}` : '' }}{{ user.is_active ? '' : ' (Inactive)' }}
                        </option>
                    </select>
                    <p v-if="form.errors[`approvers.${index}.user_id`]" class="text-sm text-red-600">
                        {{ form.errors[`approvers.${index}.user_id`] }}
                    </p>
                </div>
                <template v-else>
                    <div class="space-y-1">
                        <label :for="`new-approver-role-${index}`" class="block font-medium">Role</label>
                        <select
                            :id="`new-approver-role-${index}`"
                            v-model.number="approver.role_id"
                            class="w-full rounded border px-3 py-2"
                            required
                        >
                            <option :value="null" disabled>Select a role</option>
                            <option v-for="role in approverRoles" :key="role.id" :value="role.id">
                                {{ role.name }}
                            </option>
                        </select>
                        <p v-if="form.errors[`approvers.${index}.role_id`]" class="text-sm text-red-600">
                            {{ form.errors[`approvers.${index}.role_id`] }}
                        </p>
                    </div>
                    <div class="space-y-1">
                        <label :for="`new-approver-department-${index}`" class="block font-medium">
                            Department (optional)
                        </label>
                        <select
                            :id="`new-approver-department-${index}`"
                            v-model.number="approver.department_id"
                            class="w-full rounded border px-3 py-2"
                        >
                            <option :value="null">All departments</option>
                            <option v-for="department in departments" :key="department.id" :value="department.id">
                                {{ department.name }}
                            </option>
                        </select>
                        <p v-if="form.errors[`approvers.${index}.department_id`]" class="text-sm text-red-600">
                            {{ form.errors[`approvers.${index}.department_id`] }}
                        </p>
                    </div>
                </template>
                <button class="text-sm text-red-600 underline" type="button" @click="removeApprover(index)">
                    Remove rule
                </button>
            </div>
            <button
                class="rounded border px-3 py-2 disabled:cursor-not-allowed disabled:opacity-50"
                type="button"
                :disabled="form.approvers.length >= form.max_approvers"
                @click="addApprover"
            >
                Add approver rule
            </button>
        </fieldset>

        <div class="flex gap-3">
            <button
                class="rounded bg-primary px-4 py-2 text-primary-foreground disabled:opacity-50"
                type="submit"
                :disabled="form.processing"
            >
                {{ config ? 'Save module settings' : 'Create module' }}
            </button>
            <Link href="/admin/configs" class="rounded border px-4 py-2">Cancel</Link>
        </div>
    </form>
</template>
