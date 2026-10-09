<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Policies\AccessRequestPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreAccessRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && app(AccessRequestPolicy::class)->create($user);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'internal_beneficiary' => ['nullable', 'integer', 'exists:users,id'],
            'external_beneficiary' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'internal_beneficiary.not_self' => 'Choose another internal user or leave the beneficiary blank for a self-request.',
            'internal_beneficiary.eligible' => 'Admin and Super Admin users cannot be internal beneficiaries.',
            'external_beneficiary.nonblank' => 'The external beneficiary name cannot be blank.',
            'beneficiary.exclusive' => 'Choose either an internal or an external beneficiary, not both.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $internalBeneficiaryId = $this->input('internal_beneficiary');
                $externalBeneficiary = $this->input('external_beneficiary');

                if ($internalBeneficiaryId !== null && $externalBeneficiary !== null) {
                    $validator->errors()->add('internal_beneficiary', $this->messages()['beneficiary.exclusive']);
                    $validator->errors()->add('external_beneficiary', $this->messages()['beneficiary.exclusive']);
                }

                if ($externalBeneficiary !== null
                    && trim((string) $externalBeneficiary) === '') {
                    $validator->errors()->add(
                        'external_beneficiary',
                        $this->messages()['external_beneficiary.nonblank'],
                    );
                }

                if (! is_numeric($internalBeneficiaryId)) {
                    return;
                }

                $beneficiary = User::query()->find((int) $internalBeneficiaryId);
                $requester = $this->user();

                if ($beneficiary === null || $requester === null) {
                    return;
                }

                if ($beneficiary->is($requester)) {
                    $validator->errors()->add(
                        'internal_beneficiary',
                        $this->messages()['internal_beneficiary.not_self'],
                    );

                    return;
                }

                if (! app(AccessRequestPolicy::class)->selectInternalBeneficiary($requester, $beneficiary)) {
                    $validator->errors()->add(
                        'internal_beneficiary',
                        $this->messages()['internal_beneficiary.eligible'],
                    );
                }
            },
        ];
    }
}
