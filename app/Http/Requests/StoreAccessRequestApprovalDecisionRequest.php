<?php

namespace App\Http\Requests;

use App\Models\AccessRequestApproval;
use App\Models\User;
use App\Policies\AccessRequestApprovalPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAccessRequestApprovalDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $approval = $this->route('approval');

        return $user instanceof User
            && $approval instanceof AccessRequestApproval
            && app(AccessRequestApprovalPolicy::class)->decide($user, $approval);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'rejection_reason' => [
                Rule::requiredIf($this->input('decision') === 'rejected'),
                'nullable',
                'string',
                'max:10000',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rejection_reason.required' => 'A reason is required when rejecting an access request.',
        ];
    }
}
