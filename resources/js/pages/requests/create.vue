<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

type BeneficiaryType = 'self' | 'internal' | 'external';

defineProps<{
    accessTypes: string[];
    modules: Array<{
        id: number;
        module_name: string;
    }>;
    internalBeneficiaries: Array<{
        id: number;
        fullname: string;
        email: string;
    }>;
    priorities: string[];
}>();

const form = useForm({
    beneficiary_type: 'self' as BeneficiaryType,
    internal_beneficiary: '',
    external_beneficiary: '',
    module_lines: [
        {
            module_id: '',
            access_type: '',
            priority: '',
            justification: '',
            start_date: '',
            end_date: '',
        },
    ],
    attachments: [] as File[],
});

function setBeneficiaryType(type: BeneficiaryType) {
    form.beneficiary_type = type;
    form.internal_beneficiary = '';
    form.external_beneficiary = '';
}

function changeBeneficiaryType(event: Event) {
    const target = event.target;

    if (
        target instanceof HTMLSelectElement &&
        ['self', 'internal', 'external'].includes(target.value)
    ) {
        setBeneficiaryType(target.value as BeneficiaryType);
    }
}

function setAttachments(event: Event) {
    const input = event.target;

    if (input instanceof HTMLInputElement) {
        form.attachments = Array.from(input.files ?? []);
    }
}

function submit() {
    form.post('/requests', { forceFormData: true });
}

function addModuleLine() {
    form.module_lines.push({
        module_id: '',
        access_type: '',
        priority: '',
        justification: '',
        start_date: '',
        end_date: '',
    });
}

function removeModuleLine(index: number) {
    if (form.module_lines.length > 1) {
        form.module_lines.splice(index, 1);
    }
}

function isModuleSelected(moduleId: number, lineIndex: number): boolean {
    return form.module_lines.some(
        (line, index) =>
            index !== lineIndex && String(line.module_id) === String(moduleId),
    );
}
</script>

<template>
    <Head title="New Access Request" />

    <main class="max-w-3xl space-y-6 p-6">
        <h1 class="text-2xl font-semibold">New Access Request</h1>

        <form class="space-y-6" @submit.prevent="submit">
            <section
                class="space-y-4 rounded-lg border p-4"
                aria-labelledby="module-lines-heading"
            >
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h2
                            id="module-lines-heading"
                            class="text-lg font-medium"
                        >
                            Requested modules
                        </h2>
                        <p class="text-sm text-gray-600">
                            Add one or more modules to this request.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="rounded border px-3 py-2"
                        :disabled="form.module_lines.length >= modules.length"
                        @click="addModuleLine"
                    >
                        Add module
                    </button>
                </div>

                <p v-if="modules.length === 0" class="text-sm text-gray-600">
                    No active modules are currently available to request.
                </p>

                <div
                    v-for="(line, index) in form.module_lines"
                    :key="index"
                    class="space-y-4 rounded-md border p-4"
                >
                    <div class="flex items-end gap-3">
                        <div class="flex-1 space-y-2">
                            <label
                                :for="`module-${index}`"
                                class="block text-sm font-medium"
                            >
                                Module
                            </label>
                            <select
                                :id="`module-${index}`"
                                v-model="line.module_id"
                                class="w-full rounded border p-2"
                            >
                                <option value="" disabled>
                                    Select a module
                                </option>
                                <option
                                    v-for="module in modules"
                                    :key="module.id"
                                    :value="module.id"
                                    :disabled="
                                        isModuleSelected(module.id, index)
                                    "
                                >
                                    {{ module.module_name }}
                                </option>
                            </select>
                        </div>
                        <button
                            v-if="form.module_lines.length > 1"
                            type="button"
                            class="rounded border px-3 py-2"
                            :aria-label="`Remove module line ${index + 1}`"
                            @click="removeModuleLine(index)"
                        >
                            Remove
                        </button>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <label
                                :for="`access-type-${index}`"
                                class="block text-sm font-medium"
                            >
                                Access type
                            </label>
                            <select
                                :id="`access-type-${index}`"
                                v-model="line.access_type"
                                class="w-full rounded border p-2"
                            >
                                <option value="" disabled>
                                    Select an access type
                                </option>
                                <option
                                    v-for="accessType in accessTypes"
                                    :key="accessType"
                                    :value="accessType"
                                >
                                    {{ accessType }}
                                </option>
                            </select>
                        </div>

                        <div class="space-y-2">
                            <label
                                :for="`priority-${index}`"
                                class="block text-sm font-medium"
                            >
                                Priority
                            </label>
                            <select
                                :id="`priority-${index}`"
                                v-model="line.priority"
                                class="w-full rounded border p-2"
                            >
                                <option value="" disabled>
                                    Select a priority
                                </option>
                                <option
                                    v-for="priority in priorities"
                                    :key="priority"
                                    :value="priority"
                                >
                                    {{ priority }}
                                </option>
                            </select>
                        </div>

                        <div class="space-y-2 sm:col-span-2">
                            <label
                                :for="`justification-${index}`"
                                class="block text-sm font-medium"
                            >
                                Justification
                                <span class="font-normal text-gray-500"
                                    >(optional)</span
                                >
                            </label>
                            <textarea
                                :id="`justification-${index}`"
                                v-model="line.justification"
                                rows="3"
                                class="w-full rounded border p-2"
                            />
                        </div>

                        <div class="space-y-2">
                            <label
                                :for="`start-date-${index}`"
                                class="block text-sm font-medium"
                            >
                                Start date
                                <span class="font-normal text-gray-500"
                                    >(optional)</span
                                >
                            </label>
                            <input
                                :id="`start-date-${index}`"
                                v-model="line.start_date"
                                type="date"
                                class="w-full rounded border p-2"
                            />
                        </div>

                        <div class="space-y-2">
                            <label
                                :for="`end-date-${index}`"
                                class="block text-sm font-medium"
                            >
                                End date
                                <span class="font-normal text-gray-500"
                                    >(optional)</span
                                >
                            </label>
                            <input
                                :id="`end-date-${index}`"
                                v-model="line.end_date"
                                type="date"
                                class="w-full rounded border p-2"
                            />
                        </div>
                    </div>
                </div>
            </section>

            <section class="max-w-lg space-y-4 rounded-lg border p-4">
                <h2 class="text-lg font-medium">Beneficiary</h2>
                <div class="space-y-2">
                    <label
                        for="beneficiary_type"
                        class="block text-sm font-medium"
                        >Request for</label
                    >
                    <select
                        id="beneficiary_type"
                        :value="form.beneficiary_type"
                        class="w-full rounded border p-2"
                        @change="changeBeneficiaryType"
                    >
                        <option value="self">Myself</option>
                        <option value="internal">Another internal user</option>
                        <option value="external">
                            External vendor / third party
                        </option>
                    </select>
                </div>

                <div
                    v-if="form.beneficiary_type === 'internal'"
                    class="space-y-2"
                >
                    <label
                        for="internal_beneficiary"
                        class="block text-sm font-medium"
                    >
                        Internal beneficiary
                    </label>
                    <select
                        id="internal_beneficiary"
                        v-model="form.internal_beneficiary"
                        class="w-full rounded border p-2"
                    >
                        <option value="" disabled>
                            Select an internal user
                        </option>
                        <option
                            v-for="beneficiary in internalBeneficiaries"
                            :key="beneficiary.id"
                            :value="beneficiary.id"
                        >
                            {{ beneficiary.fullname }} ({{ beneficiary.email }})
                        </option>
                    </select>
                </div>

                <div
                    v-if="form.beneficiary_type === 'external'"
                    class="space-y-2"
                >
                    <label
                        for="external_beneficiary"
                        class="block text-sm font-medium"
                    >
                        External beneficiary name
                    </label>
                    <input
                        id="external_beneficiary"
                        v-model="form.external_beneficiary"
                        type="text"
                        maxlength="255"
                        class="w-full rounded border p-2"
                    />
                </div>
            </section>

            <section class="space-y-3 rounded-lg border p-4">
                <div>
                    <h2 class="text-lg font-medium">Attachments</h2>
                    <p class="text-sm text-gray-600">
                        Optional: up to 5 PDF or image files, 5 MB each.
                    </p>
                </div>
                <input
                    id="attachments"
                    type="file"
                    multiple
                    accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.bmp,.tif,.tiff"
                    class="block w-full text-sm"
                    @change="setAttachments"
                />
                <ul
                    v-if="form.attachments.length > 0"
                    class="list-inside list-disc text-sm"
                >
                    <li
                        v-for="file in form.attachments"
                        :key="`${file.name}-${file.lastModified}`"
                    >
                        {{ file.name }}
                    </li>
                </ul>
            </section>

            <div
                v-if="Object.keys(form.errors).length > 0"
                class="space-y-1 rounded border border-red-300 bg-red-50 p-3 text-sm text-red-800"
                role="alert"
            >
                <p v-for="(error, field) in form.errors" :key="field">
                    {{ error }}
                </p>
            </div>

            <button
                type="submit"
                class="bg-primary text-primary-foreground rounded px-4 py-2"
                :disabled="form.processing || modules.length === 0"
            >
                {{ form.processing ? 'Submitting…' : 'Submit request' }}
            </button>
        </form>

        <Link href="/dashboard" class="inline-block">Back to dashboard</Link>
    </main>
</template>
