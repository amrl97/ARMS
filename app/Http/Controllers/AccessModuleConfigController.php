<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreModuleApproverRequest;
use App\Http\Requests\StoreModuleConfigRequest;
use App\Models\AccessModuleConfig;
use App\Models\Department;
use App\Models\ModuleApprover;
use App\Models\Role;
use App\Models\User;
use App\Services\ModuleApproverResolver;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AccessModuleConfigController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Configs/index', [
            'configs' => AccessModuleConfig::withCount('moduleApprovers')
                ->latest('id')
                ->paginate(10),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Configs/create', $this->approverOptions());
    }

    public function store(StoreModuleConfigRequest $request)
    {
        $data = $request->validated();
        $approvers = $data['approvers'] ?? [];
        unset($data['approvers']);

        $config = DB::transaction(function () use ($data, $approvers): AccessModuleConfig {
            $config = AccessModuleConfig::create($data);

            foreach ($approvers as $approver) {
                $config->moduleApprovers()->create($approver);
            }

            return $config;
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Module created successfully. Configure its approvers below.',
        ]);

        return to_route('configs.edit', $config);
    }

    public function show(AccessModuleConfig $config, ModuleApproverResolver $resolver)
    {
        return Inertia::render('Admin/Configs/view', [
            'config' => $this->loadApproverRules($config, $resolver),
        ]);
    }

    public function edit(AccessModuleConfig $config, ModuleApproverResolver $resolver)
    {
        return Inertia::render('Admin/Configs/edit', [
            'config' => $this->loadApproverRules($config, $resolver),
            ...$this->approverOptions(),
        ]);
    }

    public function update(StoreModuleConfigRequest $request, AccessModuleConfig $config)
    {
        $config->update($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Module updated successfully.',
        ]);

        return to_route('configs.edit', $config);
    }

    public function storeApprover(StoreModuleApproverRequest $request)
    {
        $approver = ModuleApprover::create($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Module approver rule added successfully.',
        ]);

        return to_route('configs.edit', $approver->module_id);
    }

    public function destroyApprover(ModuleApprover $moduleApprover)
    {
        $moduleId = $moduleApprover->module_id;
        $moduleApprover->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Module approver rule removed successfully.',
        ]);

        return to_route('configs.edit', $moduleId);
    }

    /**
     * @return array<string, mixed>
     */
    private function approverOptions(): array
    {
        return [
            'approverUsers' => User::query()
                ->whereDoesntHave('roles', fn ($roles) => $roles->whereIn(
                    'roles.name',
                    User::RESTRICTED_INTERNAL_BENEFICIARY_ROLES,
                ))
                ->orderBy('fullname')
                ->get(['id', 'fullname', 'department_id', 'is_active'])
                ->load('department:id,name'),
            'approverRoles' => Role::query()
                ->whereNotIn('name', User::RESTRICTED_INTERNAL_BENEFICIARY_ROLES)
                ->orderBy('name')
                ->get(['id', 'name']),
            'departments' => Department::query()->orderBy('name')->get(['id', 'name']),
        ];
    }

    private function loadApproverRules(
        AccessModuleConfig $config,
        ModuleApproverResolver $resolver,
    ): AccessModuleConfig
    {
        $config->load([
            'moduleApprovers.user.department',
            'moduleApprovers.user.roles',
            'moduleApprovers.role',
            'moduleApprovers.department',
        ]);

        foreach ($config->moduleApprovers as $rule) {
            $rule->setAttribute('resolved_user_count', $resolver->resolve($rule)->count());
        }

        return $config;
    }
}
