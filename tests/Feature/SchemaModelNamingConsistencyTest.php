<?php

use App\Models\AccessModuleConfig;
use App\Models\AccessRequest;
use App\Models\AccessRequestApproval;
use App\Models\AccessRequestAttachment;
use App\Models\AccessRequestComment;
use App\Models\AccessRequestDetail;
use App\Models\Department;
use App\Models\ModuleApprover;
use App\Models\Role;
use App\Models\User;

test('core model relationships use the foreign keys declared by their migrations', function () {
    $user = new User();
    $role = new Role();
    $department = new Department();
    $request = new AccessRequest();
    $detail = new AccessRequestDetail();
    $approval = new AccessRequestApproval();
    $moduleApprover = new ModuleApprover();
    $comment = new AccessRequestComment();
    $attachment = new AccessRequestAttachment();

    expect($user->department()->getForeignKeyName())->toBe('department_id')
        ->and($user->requests()->getForeignKeyName())->toBe('requested_by')
        ->and($user->internalBeneficiaryRequests()->getForeignKeyName())->toBe('internal_beneficiary')
        ->and($user->comments()->getForeignKeyName())->toBe('commented_by')
        ->and($user->approvals()->getForeignKeyName())->toBe('approver_id')
        ->and($user->moduleApprovers()->getForeignKeyName())->toBe('user_id')
        ->and($user->roles()->getTable())->toBe('user_has_roles')
        ->and($user->roles()->getForeignPivotKeyName())->toBe('user_id')
        ->and($user->roles()->getRelatedPivotKeyName())->toBe('role_id')
        ->and($role->moduleApprovers()->getForeignKeyName())->toBe('role_id')
        ->and($role->users()->getTable())->toBe('user_has_roles')
        ->and($role->users()->getForeignPivotKeyName())->toBe('role_id')
        ->and($role->users()->getRelatedPivotKeyName())->toBe('user_id')
        ->and($department->users()->getForeignKeyName())->toBe('department_id')
        ->and($department->moduleApprovers()->getForeignKeyName())->toBe('department_id')
        ->and($request->requestedBy()->getForeignKeyName())->toBe('requested_by')
        ->and($request->internalBeneficiary()->getForeignKeyName())->toBe('internal_beneficiary')
        ->and($request->details()->getForeignKeyName())->toBe('request_id')
        ->and($request->attachments()->getForeignKeyName())->toBe('request_id')
        ->and($request->comments()->getForeignKeyName())->toBe('request_id')
        ->and($detail->request()->getForeignKeyName())->toBe('request_id')
        ->and($detail->module()->getForeignKeyName())->toBe('module_id')
        ->and($detail->approvals()->getForeignKeyName())->toBe('request_detail_id')
        ->and($approval->requestDetail()->getForeignKeyName())->toBe('request_detail_id')
        ->and($approval->approver()->getForeignKeyName())->toBe('approver_id')
        ->and($moduleApprover->module()->getForeignKeyName())->toBe('module_id')
        ->and($moduleApprover->user()->getForeignKeyName())->toBe('user_id')
        ->and($moduleApprover->role()->getForeignKeyName())->toBe('role_id')
        ->and($moduleApprover->department()->getForeignKeyName())->toBe('department_id')
        ->and($comment->request()->getForeignKeyName())->toBe('request_id')
        ->and($comment->commentedBy()->getForeignKeyName())->toBe('commented_by')
        ->and($attachment->request()->getForeignKeyName())->toBe('request_id')
        ->and((new AccessModuleConfig())->moduleApprovers()->getForeignKeyName())->toBe('module_id');
});
