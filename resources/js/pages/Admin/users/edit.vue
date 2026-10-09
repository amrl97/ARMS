<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    user: {
        id: number;
        fullname: string;
        email: string;
        department_id: number;
        is_active: number;
    };
    departments: Array<{ id: number; name: string }>;
    roles: Array<{ id: number; name: string }>;
    userRoleIds: number[];
}>();

const form = useForm({
    fullname: props.user.fullname,
    email: props.user.email,
    password: '',                    // blank = keep current password
    password_confirmation: '',
    department_id: props.user.department_id,
    role_ids: props.userRoleIds,
    is_active: props.user.is_active,
});

const roleError = computed(() =>
    Object.entries(form.errors).find(([key]) => key.startsWith('role_ids.'))?.[1],
);

function submit() {
    form.put(`/users/${props.user.id}`);
}
</script>

<template>
    <Head title="Edit User" />

    <div class="max-w-lg p-6">
        <h1 class="mb-4 text-2xl font-semibold">Edit User</h1>

        <form @submit.prevent="submit" class="space-y-4">
            <div>
                <label class="block">Full Name</label>
                <input v-model="form.fullname" type="text" class="w-full rounded border p-2" />
                <p v-if="form.errors.fullname" class="text-sm text-red-600">{{ form.errors.fullname }}</p>
            </div>

            <div>
                <label class="block">Email</label>
                <input v-model="form.email" type="email" class="w-full rounded border p-2" />
                <p v-if="form.errors.email" class="text-sm text-red-600">{{ form.errors.email }}</p>
            </div>

            <div>
                <label class="block">Password</label>
                <input v-model="form.password" type="password" class="w-full rounded border p-2" />
                <p v-if="form.errors.password" class="text-sm text-red-600">{{ form.errors.password }}</p>
            </div>

            <div>
                <label class="block">Confirm Password</label>
                <input v-model="form.password_confirmation" type="password" class="w-full rounded border p-2" />
            </div>

            <div>
                <label class="block">Department</label>
                <select v-model="form.department_id" class="w-full rounded border p-2">
                    <option value="" disabled>Select department</option>
                    <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                </select>
                <p v-if="form.errors.department_id" class="text-sm text-red-600">{{ form.errors.department_id }}</p>
            </div>

            <div>
                <label class="block">Roles</label>
                <select v-model="form.role_ids" multiple class="w-full rounded border p-2">
                    <option v-for="role in roles" :key="role.id" :value="role.id">{{ role.name }}</option>
                </select>
                <p v-if="form.errors.role_ids" class="text-sm text-red-600">{{ form.errors.role_ids }}</p>
                <p v-if="roleError" class="text-sm text-red-600">{{ roleError }}</p>
            </div>

            <div>
                <label class="block">Status</label>
                <select v-model="form.is_active" class="w-full rounded border p-2">
                    <option :value="1">Active</option>
                    <option :value="0">Inactive</option>
                </select>
            </div>

            <div class="flex gap-3">
                <button type="submit" :disabled="form.processing" class="rounded bg-black px-4 py-2 text-white">
                    Save
                </button>
                <Link href="/users" class="px-4 py-2">Cancel</Link>
            </div>
        </form>
    </div>
</template>