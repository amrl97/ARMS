<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\User;
use App\Models\Department;
use App\Models\Role;
use App\Services\RoleAssignmentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Inertia::render ('Admin/users/index', [
            'users' => User::with(['department', 'roles'])
            ->latest()
            ->paginate(10),
            ]
        );
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('Admin/users/create', [
                'departments' => Department::orderBy('name')->get(['id', 'name']),
                'roles' => Role::orderBy('name')->get(['id', 'name']),
            ]
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, RoleAssignmentService $roleAssignmentService)
    {
        $data = $request->validate([
            'fullname' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'department_id' => ['required', 'exists:departments,id'],
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['required', 'integer', 'distinct', 'exists:roles,id'],
            'is_active' => ['required', 'in:0,1'],
        ]);

        DB::transaction(function () use ($data, $roleAssignmentService): void {
            $roleIds = $data['role_ids'];
            unset($data['role_ids']);

            $user = User::create($data);
            $roleAssignmentService->assignRoles($user, $roleIds);
        });

        return to_route('users.index')
            ->with('success', 'User created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        return Inertia::render('Admin/users/view', [
            'user' => $user
            ->load([
                'department',
                'roles',
            ])
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $user)
    {
        return Inertia::render('Admin/users/edit', [
            'user' => $user,
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'roles' => Role::orderBy('name')->get(['id', 'name']),
            'userRoleIds' => $user->roles()->pluck('roles.id'),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user, RoleAssignmentService $roleAssignmentService)
    {
        $data = $request->validate([
            'fullname' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'department_id' => ['required', 'exists:departments,id'],
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['required', 'integer', 'distinct', 'exists:roles,id'],
            'is_active' => ['required', 'in:0,1'],
        ]);

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $roleIds = $data['role_ids'];
        unset($data['role_ids']);

        $roleAssignmentService->updateUser($user, $data, $roleIds);

        return to_route('users.index')
            ->with('success', 'User updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, User $user)
    {
        if ( $user->is($request->user())) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        $user->delete();

        return to_route('users.index')
            ->with('success', 'User deleted successfully');
    }
}
