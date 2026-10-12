<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use App\Services\RoleAssignmentService;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Inertia::render('Admin/users/index', [
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
    public function store(StoreUserRequest $request, RoleAssignmentService $roleAssignmentService)
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $roleAssignmentService): void {
            $roleIds = $data['role_ids'];
            unset($data['role_ids']);

            $user = User::create($data);
            $roleAssignmentService->assignRoles($user, $roleIds);
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'User created successfully.',
        ]);

        return to_route('users.index');
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
                ]),
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
    public function update(StoreUserRequest $request, User $user, RoleAssignmentService $roleAssignmentService)
    {
        $data = $request->validated();

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $roleIds = $data['role_ids'];
        unset($data['role_ids']);

        $roleAssignmentService->updateUser($user, $data, $roleIds);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'User updated successfully.',
        ]);

        return to_route('users.index');
    }
}
