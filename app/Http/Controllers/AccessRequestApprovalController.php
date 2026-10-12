<?php

namespace App\Http\Controllers;

use App\Enums\ApprovalStatus;
use App\Http\Requests\StoreAccessRequestApprovalDecisionRequest;
use App\Models\AccessRequestApproval;
use App\Models\User;
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

        $approvals = AccessRequestApproval::query()
            ->where('approver_id', $approver->getKey())
            ->where('status', ApprovalStatus::Pending->value)
            ->with([
                'requestDetail.module',
                'requestDetail.request.requestedBy',
                'requestDetail.request.internalBeneficiary',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->through(static function (AccessRequestApproval $approval): array {
                $detail = $approval->requestDetail;
                $accessRequest = $detail->request;
                $beneficiary = $accessRequest->resolveBeneficiary();

                return [
                    'id' => $approval->getKey(),
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
            });

        return Inertia::render('approvals/index', [
            'approvals' => $approvals,
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
