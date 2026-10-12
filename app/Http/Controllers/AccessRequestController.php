<?php

namespace App\Http\Controllers;

use App\Enums\AccessType;
use App\Enums\RequestPriority;
use App\Http\Requests\StoreAccessRequestRequest;
use App\Models\AccessModuleConfig;
use App\Models\AccessRequest;
use App\Models\User;
use App\Policies\AccessRequestPolicy;
use App\Services\AccessRequestCancellationService;
use App\Services\AccessRequestSubmissionService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

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

        return redirect()->route('requests.create');
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
    public function show(AccessRequest $accessRequest)
    {
        //
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
