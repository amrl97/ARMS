<?php

namespace App\Http\Requests;

use App\Enums\AccessType;
use App\Enums\RequestPriority;
use App\Models\User;
use App\Policies\AccessRequestPolicy;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

class StoreAccessRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && app(AccessRequestPolicy::class)->create($user);
    }

    /**
     * @return array<string, array<int, string|\Stringable>>
     */
    public function rules(): array
    {
        $rules = [
            'beneficiary_type' => ['required', Rule::in(['self', 'internal', 'external'])],
            'internal_beneficiary' => ['nullable', 'required_if:beneficiary_type,internal', 'integer', 'exists:users,id'],
            'external_beneficiary' => ['nullable', 'required_if:beneficiary_type,external', 'string', 'max:255'],
            'module_lines' => ['required', 'array', 'min:1'],
            'module_lines.*' => ['required', 'array:module_id,access_type,priority,justification,start_date,end_date'],
            'module_lines.*.module_id' => [
                'required',
                'integer',
                Rule::exists('access_module_configs', 'id')->where('is_active', true),
                'distinct',
            ],
            'module_lines.*.access_type' => ['required', new Enum(AccessType::class)],
            'module_lines.*.priority' => ['required', new Enum(RequestPriority::class)],
            'module_lines.*.justification' => ['nullable', 'string', 'max:10000'],
            'attachments' => ['sometimes', 'array', 'max:5'],
            'attachments.*' => ['file', 'mimes:pdf,jpg,jpeg,png,gif,webp,bmp,tif,tiff', 'max:5120'],
        ];

        $moduleLines = $this->input('module_lines', []);

        foreach (is_array($moduleLines) ? $moduleLines : [] as $index => $line) {
            if (! is_array($line)) {
                continue;
            }

            $rules["module_lines.{$index}.start_date"] = ['nullable', 'date_format:Y-m-d'];
            $rules["module_lines.{$index}.end_date"] = ['nullable', 'date_format:Y-m-d'];

            if (! empty($line['start_date']) && ! empty($line['end_date'])) {
                $rules["module_lines.{$index}.end_date"][] = "after_or_equal:module_lines.{$index}.start_date";
            }
        }

        return $rules;
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
            'module_lines.*.module_id.exists' => 'A selected module is inactive or unavailable. Choose an active module or remove it from the request.',
            'module_lines.*.end_date.after_or_equal' => 'The end date must be on or after the start date.',
            'attachments.max' => 'A request can include no more than five attachments.',
            'attachments.*.mimes' => 'Attachments must be PDF files or images.',
            'attachments.*.max' => 'Each attachment must be no larger than 5 MB.',
        ];
    }

    /**
     * @return array<int, Closure(Validator): void>
     */
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

                if ($this->input('beneficiary_type') === 'self'
                    && ($internalBeneficiaryId !== null || $externalBeneficiary !== null)) {
                    $validator->errors()->add(
                        'beneficiary_type',
                        'A self-request cannot include another beneficiary.',
                    );
                }

                if ($this->input('beneficiary_type') !== 'external') {
                    $moduleLines = $this->input('module_lines', []);

                    foreach (is_array($moduleLines) ? $moduleLines : [] as $index => $line) {
                        if (is_array($line)
                            && ($line['access_type'] ?? null) === AccessType::VendorThirdParty->value) {
                            $validator->errors()->add(
                                "module_lines.{$index}.access_type",
                                'Vendor/3rd Party access is only valid for an external beneficiary.',
                            );
                        }
                    }
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
