<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreModuleApproverRequest;
use App\Http\Requests\StoreModuleConfigRequest;
use App\Models\AccessModuleConfig;
use App\Models\Department;
use App\Models\ModuleApprover;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Query\Builder as QueryBuilder;
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

    public function show(AccessModuleConfig $config)
    {
        return Inertia::render('Admin/Configs/view', [
            'config' => $this->loadApproverRules($config),
        ]);
    }

    public function edit(AccessModuleConfig $config)
    {
        return Inertia::render('Admin/Configs/edit', [
            'config' => $this->loadApproverRules($config),
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

    private function loadApproverRules(AccessModuleConfig $config): AccessModuleConfig
    {
        $config->load([
            'moduleApprovers.user.department',
            'moduleApprovers.user.roles',
            'moduleApprovers.role',
            'moduleApprovers.department',
        ]);

        $rules = $config->moduleApprovers;
        $directUserIds = $rules->whereNotNull('user_id')->pluck('user_id')->all();
        $eligibleDirectUserIds = User::query()
            ->whereKey($directUserIds)
            ->where('is_active', true)
            ->whereDoesntHave('roles', fn ($roles) => $roles->whereIn(
                'roles.name',
                User::RESTRICTED_INTERNAL_BENEFICIARY_ROLES,
            ))
            ->pluck('id')
            ->all();

        $roleRuleIds = $rules->whereNotNull('role_id')->pluck('id')->all();
        $roleResolutionCounts = DB::table('module_approvers as module_rule')
            ->join('roles as configured_role', 'configured_role.id', '=', 'module_rule.role_id')
            ->join('user_has_roles', 'user_has_roles.role_id', '=', 'module_rule.role_id')
            ->join('users', 'users.id', '=', 'user_has_roles.user_id')
            ->whereIn('module_rule.id', $roleRuleIds)
            ->where('users.is_active', true)
            ->whereNotIn('configured_role.name', User::RESTRICTED_INTERNAL_BENEFICIARY_ROLES)
            ->where(function (QueryBuilder $query): void {
                $query->whereNull('module_rule.department_id')
                    ->orWhereColumn('module_rule.department_id', 'users.department_id');
            })
            ->whereNotExists(function (QueryBuilder $query): void {
                $query->selectRaw('1')
                    ->from('user_has_roles as restricted_user_role')
                    ->join('roles as restricted_role', 'restricted_role.id', '=', 'restricted_user_role.role_id')
                    ->whereColumn('restricted_user_role.user_id', 'users.id')
                    ->whereIn('restricted_role.name', User::RESTRICTED_INTERNAL_BENEFICIARY_ROLES);
            })
            ->groupBy('module_rule.id')
            ->select('module_rule.id')
            ->selectRaw('COUNT(DISTINCT users.id) AS resolved_user_count')
            ->pluck('resolved_user_count', 'id');

        foreach ($rules as $rule) {
            $resolvedUserCount = $rule->user_id !== null
                ? (int) in_array($rule->user_id, $eligibleDirectUserIds)
                : (int) ($roleResolutionCounts[$rule->id] ?? 0);

            $rule->setAttribute('resolved_user_count', $resolvedUserCount);
        }

        return $config;
    }
}
