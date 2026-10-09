<?php

namespace App\Http\Requests;

use App\Models\AccessModuleConfig;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreModuleApproverRequest extends FormRequest
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
            'module_id' => ['required', 'exists:access_module_configs,id'],
            'user_id' => ['nullable', 'exists:users,id'],
            'role_id' => ['nullable', 'exists:roles,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $hasUser = filled($this->user_id);
                $hasRole = filled($this->role_id);
                $hasDept = filled($this->department_id);

                if ($hasUser === $hasRole) {
                    $validator->errors()->add(
                        'user_id',
                        'Choose either a user or a role, not both and not neither.'
                    );
                }

                if ($hasDept && ! $hasRole) {
                    $validator->errors()->add(
                        'department_id',
                        'A department can only be set together with a role.'
                    );
                }

                $user = $hasUser ? User::find($this->user_id) : null;
                if ($user?->roles()->whereIn('name', User::RESTRICTED_INTERNAL_BENEFICIARY_ROLES)->exists()) {
                    $validator->errors()->add(
                        'user_id',
                        'Admin and Super Admin users cannot be module approvers.'
                    );
                }

                $role = $hasRole ? Role::find($this->role_id) : null;
                if ($role && in_array($role->name, User::RESTRICTED_INTERNAL_BENEFICIARY_ROLES, true)) {
                    $validator->errors()->add(
                        'role_id',
                        'The Admin and Super Admin roles cannot be module approvers.'
                    );
                }

                if ($hasUser !== $hasRole) {
                    $module = AccessModuleConfig::find($this->input('module_id'));

                    if ($module && $module->moduleApprovers()->count() >= $module->max_approvers) {
                        $validator->errors()->add(
                            'module_id',
                            'This module has reached its maximum number of approver rows.'
                        );
                    }
                }
            },
        ];
    }
}
