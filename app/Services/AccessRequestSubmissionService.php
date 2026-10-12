<?php

namespace App\Services;

use App\Enums\AccessRequestDetailStatus;
use App\Models\AccessModuleConfig;
use App\Models\AccessRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class AccessRequestSubmissionService
{

    // The constructor is used to inject the ModuleApproverResolver dependency into the service. This allows the service to use the resolver for determining eligible approvers for access requests.
    public function __construct(
        private readonly ModuleApproverResolver $moduleApproverResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * 
     * The method performs several operations within a database transaction to ensure data integrity. 
     * It validates the module lines, checks for eligible final approvers, creates the access request and its details and handles file attachments. 
     * If any part of the process fails, it rolls back the transaction and cleans up any stored files.
     */

    public function submit(User $requester, array $data): AccessRequest
    {
        $storedPaths = [];

        try {

        // Start a database transaction to ensure atomicity of the access request submission process
            return DB::transaction(function () use ($requester, $data, &$storedPaths): AccessRequest {
                
            /*
             * Validating the module lines provided in the access request submission.
             */

                $lines = $data['module_lines'] ?? [];

                // Validate that at least one module line is provided for an access request
                if ($lines === []) {
                    throw ValidationException::withMessages([
                        'module_lines' => 'Add at least one module line to submit a request.',
                    ]);
                }

                // Extract module IDs from the provided module lines
                $moduleIds = array_map(
                    static fn (array $line): int => (int) $line['module_id'],
                    $lines,
                );
                $modules = AccessModuleConfig::query()
                    ->whereKey($moduleIds)
                    ->availableForRequests()
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                foreach ($lines as $index => $line) {
                    if (! $modules->has((int) $line['module_id'])) {
                        throw ValidationException::withMessages([
                            "module_lines.{$index}.module_id" => 'A selected module is inactive or unavailable. Choose an active module or remove it from the request.',
                        ]);
                    }
                }


            /*
             * Checking for eligible final approvers (Admins) for the access request.    
             */  

                $finalApprovers = $this->getEligibleFinalApprovers($requester, $data);

                if ($finalApprovers->isEmpty()) {
                    throw ValidationException::withMessages([
                        'module_lines' => 'No active Admin is available for final approval. Contact an administrator before submitting this request.',
                    ]);
                }

            /*
             * Creating the access request record with the details, including determining approvers for each module line and handling file attachments.    
             */

                // Determine the beneficiary type (self, internal, or external) and create the access request record
                $beneficiaryType = $data['beneficiary_type'] ?? 'self';
                $accessRequest = AccessRequest::create([
                    'requested_by' => $requester->getKey(),
                    'internal_beneficiary' => $beneficiaryType === 'internal'
                        ? $data['internal_beneficiary']
                        : null,
                    'external_beneficiary' => $beneficiaryType === 'external'
                        ? trim($data['external_beneficiary'])
                        : null,
                ]);

                // Load the requestedBy and internalBeneficiary relationships for the access request
                $accessRequest->load(['requestedBy', 'internalBeneficiary']);
                $beneficiary = $accessRequest->resolveBeneficiary();

                // Repeat the process of creating access request details and determining approvers for each module line
                foreach ($lines as $index => $line) {
                    /** @var AccessModuleConfig $module */
                    $module = $modules->get((int) $line['module_id']);
                    $module->setRelation(
                        'moduleApprovers',
                        $module->moduleApprovers()->lockForUpdate()->get(),
                    );

                    $detail = $accessRequest->details()->create([
                        'module_id' => $module->getKey(),
                        'access_type' => $line['access_type'],
                        'priority' => $line['priority'],
                        'justification' => $line['justification'] ?? null,
                        'start_date' => $line['start_date'] ?? null,
                        'end_date' => $line['end_date'] ?? null,
                        'status' => AccessRequestDetailStatus::Pending->value,
                    ]);

                    $resolution = $this->moduleApproverResolver->resolveForSubmission(
                        $module,
                        $requester,
                        $beneficiary,
                    );

                    // If the resolution indicates that configuration is required, throw a specific message for the module line
                    if ($resolution->requiresConfiguration()) {
                        throw ValidationException::withMessages([
                            "module_lines.{$index}.module_id" => 'No active eligible approver is configured for this module. Contact an administrator to review its approver rules.',
                        ]);
                    }

                    if ($module->max_approvers === 0 || $resolution->skipModuleStep) {
                        $approvers = $finalApprovers;
                        $step = 'final';
                    } else {
                        $approvers = $resolution->approvers;
                        $step = 'module';
                    }

                    foreach ($approvers as $approver) {
                        $detail->approvals()->create([
                            'step' => $step,
                            'approver_id' => $approver->getKey(),
                            'status' => 'pending',
                        ]);
                    }
                }

                foreach ($data['attachments'] ?? [] as $index => $file) {
                    if (! $file instanceof UploadedFile) {
                        throw ValidationException::withMessages([
                            "attachments.{$index}" => 'The attachment upload is invalid.',
                        ]);
                    }

                    $path = $file->store("access-requests/{$accessRequest->getKey()}", 'local');

                    if ($path === false) {
                        throw new RuntimeException('The attachment could not be stored.');
                    }

                    $storedPaths[] = $path;
                    $fileSize = $file->getSize();

                    if ($fileSize === false) {
                        throw new RuntimeException('The attachment size could not be determined.');
                    }

                    $accessRequest->attachments()->create([
                        'file_name' => $file->getClientOriginalName(),
                        'file_path' => $path,
                        'file_size' => $fileSize,
                    ]);
                }

                return $accessRequest;
            });
        } catch (Throwable $exception) {
            if ($storedPaths !== []) {
                try {
                    if (! Storage::disk('local')->delete($storedPaths)) {
                        Log::error('Failed to remove uploaded request attachments after submission rollback.', [
                            'paths' => $storedPaths,
                            'exception' => $exception,
                        ]);
                    }
                } catch (Throwable $cleanupException) {
                    Log::error('Could not clean up request attachments after submission rollback.', [
                        'paths' => $storedPaths,
                        'submission_exception' => $exception,
                        'cleanup_exception' => $cleanupException,
                    ]);
                }
            }

            throw $exception;
        }
    }

    /**
     * Check that an active Admin exists without creating a final approval row.
     *
     * @param  array<string, mixed>  $data
     * @return Collection<int, User>
     */
    private function getEligibleFinalApprovers(User $requester, array $data): Collection
    {
        $excludedUserIds = [$requester->getKey()];

        if (($data['beneficiary_type'] ?? 'self') === 'internal') {
            $excludedUserIds[] = (int) $data['internal_beneficiary'];
        }

        return User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn (Builder $roles): Builder => $roles->where('name', 'Admin'))
            ->whereNotIn('id', $excludedUserIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }
}
