<?php

namespace App\Http\Controllers;

use App\Models\AccessRequest;
use App\Models\User;
use App\Policies\AccessRequestPolicy;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AccessRequestController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request, AccessRequestPolicy $policy)
    {
        abort_unless($policy->create($request->user()), 403);

        return Inertia::render('requests/create', [
            'internalBeneficiaries' => User::query()
                ->eligibleInternalBeneficiaries()
                ->whereKeyNot($request->user()->getKey())
                ->orderBy('fullname')
                ->get(['id', 'fullname', 'email']),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
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
