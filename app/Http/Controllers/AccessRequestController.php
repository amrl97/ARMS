<?php

namespace App\Http\Controllers;

use App\Enums\AccessType;
use App\Enums\RequestPriority;
use App\Http\Requests\StoreAccessRequestRequest;
use App\Models\AccessModuleConfig;
use App\Models\AccessRequest;
use App\Models\AccessRequestAttachment;
use App\Models\User;
use App\Policies\AccessRequestPolicy;
use App\Services\AccessRequestCancellationService;
use App\Services\AccessRequestSubmissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccessRequestController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, AccessRequestPolicy $policy)
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);
        abort_unless($policy->viewAny($user), 403);

        return Inertia::render('requests/index', [
            'requests' => $policy->viewableRequests($user)
                ->with(['requestedBy', 'internalBeneficiary', 'details.module'])
                ->latest()
                ->paginate(10),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request, AccessRequestPolicy $policy): Response
    {

        // Abort with a 403 Forbidden response if the user is not authorized to create an access request
        abort_unless($policy->create($request->user()), 403);

        return Inertia::render('requests/create', [
            'accessTypes' => array_map(
                static fn (AccessType $accessType): string => $accessType->value,
                AccessType::cases(),
            ),
            'modules' => AccessModuleConfig::query()
                ->availableForRequests()
                ->orderBy('module_name')
                ->get(['id', 'module_name']),
            'internalBeneficiaries' => User::query()
                ->eligibleInternalBeneficiaries()
                ->where('is_active', true)
                ->whereKeyNot($request->user()->getKey())
                ->orderBy('fullname')
                ->get(['id', 'fullname', 'email']),
            'priorities' => array_map(
                static fn (RequestPriority $priority): string => $priority->value,
                RequestPriority::cases(),
            ),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(

        // 1. Type-hint the request parameter with StoreAccessRequestRequest to ensure validation is applied
        StoreAccessRequestRequest $request,

        // 2. Type-hint the submission service parameter with AccessRequestSubmissionService to inject the service
        AccessRequestSubmissionService $submissionService,

        // 3. Specify the return type as RedirectResponse to indicate that this method will return a redirect response
    ): RedirectResponse {

        // 1. Ensure the user is authenticated and is an instance of User
        $requester = $request->user();

        // 2. Abort with a 401 Unauthorized response if the user is not authenticated or not an instance of User
        abort_unless($requester instanceof User, 401);

        // 3. Submit the access request using the submission service
        $submissionService->submit($requester, $request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Access request submitted successfully.',
        ]);

        return redirect()->route('requests.index');
    }

    public function cancel(
        Request $request,
        AccessRequest $accessRequest,
        AccessRequestPolicy $policy,
        AccessRequestCancellationService $cancellationService,
    ): RedirectResponse {
        $user = $request->user();

        abort_unless($user instanceof User, 401);
        abort_unless($policy->cancel($user, $accessRequest), 403);

        $cancelledLines = $cancellationService->cancel($user, $accessRequest);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $cancelledLines > 0
                ? 'Pending request lines were cancelled.'
                : 'There were no pending lines to cancel.',
        ]);

        return redirect()->route('requests.index');
    }

    /**
     * Display the specified resource.
     */
    public function show(
        Request $request,
        AccessRequest $accessRequest,
        AccessRequestPolicy $policy,
    ): Response {
        $user = $request->user();

        abort_unless($user instanceof User, 401);
        abort_unless($policy->view($user, $accessRequest), 403);

        $accessRequest->load([
            'requestedBy',
            'internalBeneficiary',
            'details' => fn ($query) => $query->orderBy('id'),
            'details.module',
            'details.approvals' => fn ($query) => $query->orderBy('created_at')->orderBy('id'),
            'details.approvals.approver',
            'comments' => fn ($query) => $query->orderBy('created_at')->orderBy('id'),
            'comments.commentedBy',
            'attachments' => fn ($query) => $query->orderBy('created_at')->orderBy('id'),
        ]);
        $beneficiary = $accessRequest->resolveBeneficiary();

        return Inertia::render('requests/view', [
            'accessRequest' => [
                'id' => $accessRequest->id,
                'status' => $accessRequest->status,
                'created_at' => $accessRequest->created_at,
                'requested_by' => $accessRequest->requestedBy === null
                    ? null
                    : ['fullname' => $accessRequest->requestedBy->fullname],
                'beneficiary' => $beneficiary instanceof User
                    ? $beneficiary->fullname
                    : $beneficiary,
                'details' => $accessRequest->details->map(static fn ($detail): array => [
                    'id' => $detail->id,
                    'access_type' => $detail->access_type,
                    'priority' => $detail->priority,
                    'justification' => $detail->justification,
                    'start_date' => $detail->start_date,
                    'end_date' => $detail->end_date,
                    'status' => $detail->status,
                    'module' => $detail->module === null
                        ? null
                        : ['module_name' => $detail->module->module_name],
                    'approvals' => $detail->approvals->map(static fn ($approval): array => [
                        'id' => $approval->id,
                        'step' => $approval->step,
                        'status' => $approval->status,
                        'rejection_reason' => $approval->rejection_reason,
                        'created_at' => $approval->created_at,
                        'updated_at' => $approval->updated_at,
                        'approver' => $approval->approver === null
                            ? null
                            : ['fullname' => $approval->approver->fullname],
                    ])->all(),
                ])->all(),
                'comments' => $accessRequest->comments->map(static fn ($comment): array => [
                    'id' => $comment->id,
                    'comment' => $comment->comment,
                    'created_at' => $comment->created_at,
                    'commented_by' => $comment->commentedBy === null
                        ? null
                        : ['fullname' => $comment->commentedBy->fullname],
                ])->all(),
                'attachments' => $accessRequest->attachments->map(static fn ($attachment): array => [
                    'id' => $attachment->id,
                    'file_name' => $attachment->file_name,
                    'file_size' => $attachment->file_size,
                    'created_at' => $attachment->created_at,
                ])->all(),
            ],
            'canCancel' => $policy->cancel($user, $accessRequest),
        ]);
    }

    public function downloadAttachment(
        Request $request,
        AccessRequest $accessRequest,
        AccessRequestAttachment $attachment,
        AccessRequestPolicy $policy,
    ): StreamedResponse {
        $user = $request->user();

        abort_unless($user instanceof User, 401);
        abort_unless($policy->view($user, $accessRequest), 403);
        abort_unless($accessRequest->attachments()->whereKey($attachment->getKey())->exists(), 404);
        abort_unless(Storage::disk('local')->exists($attachment->file_path), 404);

        return Storage::disk('local')->download($attachment->file_path, $attachment->file_name);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(AccessRequest $accessRequest)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, AccessRequest $accessRequest)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(AccessRequest $accessRequest)
    {
        //
    }
}
