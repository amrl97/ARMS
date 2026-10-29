<?php

namespace App\Http\Controllers;

use App\Enums\AccessRequestDetailStatus;
use App\Enums\ApprovalStatus;
use App\Enums\ApprovalStep;
use App\Http\Requests\StoreAccessRequestApprovalDecisionRequest;
use App\Models\AccessRequestApproval;
use App\Models\User;
use App\Policies\AccessRequestApprovalPolicy;
use App\Services\AccessRequestApprovalWorkflowService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

class AccessRequestApprovalController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $approver = $request->user();

        abort_unless($approver instanceof User, 401);

        $loadApprovals = static function (ApprovalStep $step) use ($approver) {
            return AccessRequestApproval::query()
                ->where('approver_id', $approver->getKey())
                ->where('status', ApprovalStatus::Pending->value)
                ->where('step', $step->value)
                ->whereHas(
                    'requestDetail',
                    fn ($details) => $details->where('status', AccessRequestDetailStatus::Pending->value),
                )
                ->with([
                    'requestDetail.module',
                    'requestDetail.request.requestedBy',
                    'requestDetail.request.internalBeneficiary',
                ])
                ->orderByDesc('created_at')
                ->orderByDesc('id');
        };

        $mapApprovals = static function (AccessRequestApproval $approval): array {
            $detail = $approval->requestDetail;
            $accessRequest = $detail->request;
            $beneficiary = $accessRequest->resolveBeneficiary();

            return [
                'id' => $approval->getKey(),
                'requestId' => $accessRequest->getKey(),
                'step' => $approval->step,
                'module' => $detail->module->module_name,
                'accessType' => $detail->access_type,
                'priority' => $detail->priority,
                'justification' => $detail->justification,
                'startDate' => $detail->start_date,
                'endDate' => $detail->end_date,
                'requester' => $accessRequest->requestedBy->fullname,
                'beneficiary' => $beneficiary instanceof User
                    ? $beneficiary->fullname
                    : $beneficiary,
                'submittedAt' => $accessRequest->created_at?->toDateTimeString(),
            ];
        };

        return Inertia::render('approvals/index', [
            'moduleApprovals' => $loadApprovals(ApprovalStep::Module)
                ->paginate(10, ['*'], 'modulePage')
                ->through($mapApprovals),
            'finalApprovals' => $loadApprovals(ApprovalStep::Final)
                ->paginate(10, ['*'], 'finalPage')
                ->through($mapApprovals),
        ]);
    }

    public function show(
        Request $request,
        AccessRequestApproval $approval,
        AccessRequestApprovalPolicy $policy,
    ): Response {
        $approver = $request->user();

        abort_unless($approver instanceof User, 401);
        abort_unless($policy->decide($approver, $approval), 403);

        $approval->load([
            'approver',
            'requestDetail.module',
            'requestDetail.approvals' => fn ($query) => $query->orderBy('created_at')->orderBy('id'),
            'requestDetail.approvals.approver',
            'requestDetail.request.requestedBy',
            'requestDetail.request.internalBeneficiary',
        ]);

        $detail = $approval->requestDetail;
        $accessRequest = $detail->request;
        $beneficiary = $accessRequest->resolveBeneficiary();

        return Inertia::render('approvals/show', [
            'approval' => [
                'id' => $approval->getKey(),
                'step' => $approval->step,
                'status' => $approval->status,
                'requestId' => $accessRequest->getKey(),
                'requestStatus' => $accessRequest->status,
                'submittedAt' => $accessRequest->created_at?->toDateTimeString(),
                'module' => $detail->module->module_name,
                'accessType' => $detail->access_type,
                'priority' => $detail->priority,
                'justification' => $detail->justification,
                'startDate' => $detail->start_date,
                'endDate' => $detail->end_date,
                'requester' => $accessRequest->requestedBy->fullname,
                'beneficiary' => $beneficiary instanceof User
                    ? $beneficiary->fullname
                    : $beneficiary,
                'history' => $detail->approvals->map(static fn (AccessRequestApproval $historyItem): array => [
                    'step' => $historyItem->step,
                    'status' => $historyItem->status,
                    'approver' => $historyItem->approver?->fullname,
                    'rejectionReason' => $historyItem->rejection_reason,
                    'updatedAt' => $historyItem->updated_at?->toDateTimeString(),
                ])->all(),
            ],
        ]);
    }

    public function decide(
        StoreAccessRequestApprovalDecisionRequest $request,
        AccessRequestApproval $approval,
        AccessRequestApprovalWorkflowService $workflow,
    ): RedirectResponse {
        $approver = $request->user();

        abort_unless($approver instanceof User, 401);

        $data = $request->validated();
        $workflow->decide(
            $approver,
            $approval,
            ApprovalStatus::from($data['decision']),
            $data['rejection_reason'] ?? null,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $data['decision'] === ApprovalStatus::Approved->value
                ? 'Access request approved.'
                : 'Access request rejected.',
        ]);

        return redirect()->route('approvals.index');
    }
}
