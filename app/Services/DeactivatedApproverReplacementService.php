<?php

namespace App\Services;

use App\Enums\AccessRequestDetailStatus;
use App\Enums\ApprovalStatus;
use App\Enums\ApprovalStep;
use App\Models\AccessModuleConfig;
use App\Models\AccessRequestDetail;
use App\Models\User;
use App\Notifications\ApprovalCoverageUnavailable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class DeactivatedApproverReplacementService
{
    public function __construct(
        private readonly ModuleApproverResolver $moduleApproverResolver,
    ) {}

    public function replace(User $deactivatedUser): void
    {
        $coverageIssues = [];
        $details = AccessRequestDetail::query()
            ->where('status', AccessRequestDetailStatus::Pending->value)
            ->whereHas('approvals', static fn ($approvals) => $approvals
                ->where('approver_id', $deactivatedUser->getKey())
                ->where('status', ApprovalStatus::Pending->value))
            ->lockForUpdate()
            ->get();

        foreach ($details as $detail) {
            $approvals = $detail->approvals()
                ->where('approver_id', $deactivatedUser->getKey())
                ->where('status', ApprovalStatus::Pending->value)
                ->lockForUpdate()
                ->get();

            foreach ($approvals as $approval) {
                $approval->forceFill(['status' => ApprovalStatus::Skipped->value])->save();
                $step = ApprovalStep::from($approval->step);
                $this->assignEligibleReplacements($detail, $step);

                $required = $this->requiredApprovalCount($detail, $step);
                $available = $this->activePendingApprovalCount($detail, $step);

                if ($available < $required) {
                    $request = $detail->request()->firstOrFail();
                    $coverageIssues[] = [
                        'request_id' => (int) $request->getKey(),
                        'detail_id' => (int) $detail->getKey(),
                        'module_name' => $detail->module()->value('module_name'),
                        'step' => $step->value,
                        'available' => $available,
                        'required' => $required,
                    ];
                }
            }

            $detail->request()->firstOrFail()->recalculateStatus();
        }

        if ($coverageIssues === []) {
            return;
        }

        $superAdmins = User::query()
            ->where('is_active', true)
            ->whereHas('roles', static fn ($roles) => $roles->where('name', 'Super Admin'))
            ->get();

        if ($superAdmins->isEmpty()) {
            Log::warning('Unable to notify Super Admins that approval coverage is insufficient.', [
                'issues' => $coverageIssues,
            ]);

            return;
        }

        Notification::send(
            $superAdmins,
            new ApprovalCoverageUnavailable($coverageIssues),
        );
    }

    private function assignEligibleReplacements(AccessRequestDetail $detail, ApprovalStep $step): void
    {
        $request = $detail->request()->firstOrFail();
        $existingApproverIds = $detail->approvals()
            ->pluck('approver_id')
            ->map(static fn (int|string $id): int => (int) $id)
            ->all();
        $excludedIds = array_values(array_filter([
            (int) $request->requested_by,
            $request->internal_beneficiary === null ? null : (int) $request->internal_beneficiary,
        ]));

        $eligibleApprovers = match ($step) {
            ApprovalStep::Module => $this->resolveCurrentModuleApprovers($detail),
            ApprovalStep::Final => User::query()
                ->where('is_active', true)
                ->whereHas('roles', static fn ($roles) => $roles->where('name', 'Admin'))
                ->get(),
        };

        $replacementUsers = $eligibleApprovers
            ->reject(static fn (User $candidate): bool => in_array((int) $candidate->getKey(), $excludedIds, true)
                || in_array((int) $candidate->getKey(), $existingApproverIds, true));

        foreach ($replacementUsers as $replacement) {
            $detail->approvals()->create([
                'step' => $step->value,
                'approver_id' => $replacement->getKey(),
                'status' => ApprovalStatus::Pending->value,
            ]);
        }
    }

    private function requiredApprovalCount(AccessRequestDetail $detail, ApprovalStep $step): int
    {
        if ($step === ApprovalStep::Final) {
            return 1;
        }

        return (int) AccessModuleConfig::query()
            ->whereKey($detail->module_id)
            ->value('required_approvals');
    }

    private function activePendingApprovalCount(AccessRequestDetail $detail, ApprovalStep $step): int
    {
        $request = $detail->request()->firstOrFail();

        return $detail->approvals()
            ->where('step', $step->value)
            ->where('status', ApprovalStatus::Pending->value)
            ->whereHas('approver', static function ($approvers) use ($request): void {
                $approvers
                    ->where('is_active', true)
                    ->whereKeyNot($request->requested_by)
                    ->when(
                        $request->internal_beneficiary !== null,
                        static fn ($query) => $query->whereKeyNot($request->internal_beneficiary),
                    );
            })
            ->count();
    }

    /**
     * @return Collection<int, User>
     */
    private function resolveCurrentModuleApprovers(AccessRequestDetail $detail): Collection
    {
        $module = $detail->module()->with('moduleApprovers')->firstOrFail();

        $approvers = $module->moduleApprovers
            ->flatMap(fn ($rule): Collection => $this->moduleApproverResolver->resolve($rule))
            ->unique('id')
            ->values();

        return new Collection($approvers->all());
    }
}
