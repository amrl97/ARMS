<?php

namespace App\Http\Requests;

use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreModuleConfigRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'module_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('access_module_configs', 'module_name')->ignore($this->route('config')),
            ],
            'max_approvers' => ['required', 'integer', 'min:0', 'max:255'],
            'required_approvals' => ['required', 'integer', 'min:0', 'lte:max_approvers'],
            'is_active' => ['required', 'boolean'],
            'approvers' => ['sometimes', 'array', 'max:255'],
            'approvers.*.user_id' => ['nullable', 'exists:users,id'],
            'approvers.*.role_id' => ['nullable', 'exists:roles,id'],
            'approvers.*.department_id' => ['nullable', 'exists:departments,id'],
        ];
    }

    /**
     * Stop max_approvers from dropping below the number of approver rows it already has.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->count() > 0) {
                    return;
                }

                $module = $this->route('config');

                if ($module && (int) $this->max_approvers < $module->moduleApprovers()->count()) {
                    $validator->errors()->add(
                        'max_approvers',
                        "This module already has {$module->moduleApprovers()->count()} approver row(s). Remove some first."
                    );
                }

                $maxApprovers = (int) $this->max_approvers;
                $requiredApprovals = (int) $this->required_approvals;

                if ($maxApprovers === 0 && $requiredApprovals !== 0) {
                    $validator->errors()->add(
                        'required_approvals',
                        'When max_approvers is 0, required_approvals must also be 0.'
                    );
                }

                if ($maxApprovers > 0 && $requiredApprovals !== 1) {
                    $validator->errors()->add(
                        'required_approvals',
                        'When max_approvers is greater than 0, required_approvals must be 1 under the current workflow.'
                    );
                }

                $approvers = $this->input('approvers', []);
                if (! is_array($approvers)) {
                    return;
                }

                if (count($approvers) > $maxApprovers) {
                    $validator->errors()->add(
                        'approvers',
                        'The number of approver rules cannot exceed max_approvers.'
                    );
                }

                foreach ($approvers as $index => $approver) {
                    if (! is_array($approver)) {
                        continue;
                    }

                    $hasUser = filled($approver['user_id'] ?? null);
                    $hasRole = filled($approver['role_id'] ?? null);
                    $hasDepartment = filled($approver['department_id'] ?? null);

                    if ($hasUser === $hasRole) {
                        $validator->errors()->add(
                            "approvers.{$index}.user_id",
                            'Choose either a user or a role, not both and not neither.'
                        );
                    }

                    if ($hasDepartment && ! $hasRole) {
                        $validator->errors()->add(
                            "approvers.{$index}.department_id",
                            'A department can only be set together with a role.'
                        );
                    }

                    if ($hasUser && User::find($approver['user_id'])?->roles()
                        ->whereIn('name', User::RESTRICTED_INTERNAL_BENEFICIARY_ROLES)->exists()) {
                        $validator->errors()->add(
                            "approvers.{$index}.user_id",
                            'Admin and Super Admin users cannot be module approvers.'
                        );
                    }

                    $role = $hasRole ? Role::find($approver['role_id']) : null;
                    if ($role && in_array($role->name, User::RESTRICTED_INTERNAL_BENEFICIARY_ROLES, true)) {
                        $validator->errors()->add(
                            "approvers.{$index}.role_id",
                            'The Admin and Super Admin roles cannot be module approvers.'
                        );
                    }
                }
            },
        ];
    }
}
