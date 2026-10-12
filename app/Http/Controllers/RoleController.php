<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRoleRequest;
use App\Models\Role;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class RoleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Inertia::render('roles/index', [
            'roles' => Role::withCount(['users', 'moduleApprovers'])
                ->orderBy('name')
                ->paginate(10),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('roles/create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRoleRequest $request)
    {
        $data = $request->validated();

        Role::create($data);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Role created successfully.',
        ]);

        return to_route('roles.index');
    }

    /**
     * Display the specified resource.
     */
    public function show(Role $role)
    {
        return Inertia::render('roles/view', [
            'role' => $role->loadCount(['users', 'moduleApprovers']),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Role $role)
    {
        return Inertia::render('roles/edit', [
            'role' => $role,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(StoreRoleRequest $request, Role $role)
    {
        $data = $request->validated();

        $role->update($data);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Role updated successfully.',
        ]);

        return to_route('roles.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Role $role)
    {
        if ($role->users()->exists() || $role->moduleApprovers()->exists()) {
            throw ValidationException::withMessages([
                'role' => 'This role is assigned to users or module approver rules and cannot be deleted.',
            ]);
        }

        $role->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Role deleted successfully.',
        ]);

        return to_route('roles.index');
    }
}
