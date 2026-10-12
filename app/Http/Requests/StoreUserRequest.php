<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $isInactive = $this->input('is_active') === false
            || $this->input('is_active') === 0
            || $this->input('is_active') === '0';

        if (! $this->exists('is_active') || ! $isInactive) {
            return true;
        }

        $user = $this->route('user');
        $isDeactivation = ! ($user instanceof User) || $user->is_active;

        return ! $isDeactivation || $this->user()?->hasRole('Admin') === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'fullname' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user instanceof User ? $user : null),
            ],
            'password' => [
                $this->isMethod('POST') ? 'required' : 'nullable',
                'string',
                'min:8',
                'confirmed',
            ],
            'department_id' => ['required', 'exists:departments,id'],
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['required', 'integer', 'distinct', 'exists:roles,id'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
