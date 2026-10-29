<?php

use App\Enums\AccessRequestDetailStatus;
use App\Enums\AccessType;
use App\Enums\ApprovalStatus;
use App\Enums\ApprovalStep;
use App\Enums\RequestPriority;
use App\Models\AccessModuleConfig;
use App\Models\AccessRequest;
use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function makeRequestDetailViewUser(string $name): User
{
    static $sequence = 0;
    $sequence++;
    $department = Department::create(['name' => "Request Detail View Department {$sequence}"]);

    return User::factory()->create([
        'fullname' => $name,
        'department_id' => $department->id,
    ]);
}

function makeRequestDetailViewRequest(User $requester, User $approver): AccessRequest
{
    static $sequence = 0;
    $sequence++;
    $module = AccessModuleConfig::create([
        'module_name' => "Request History Module {$sequence}",
        'max_approvers' => 1,
        'required_approvals' => 1,
        'is_active' => false,
    ]);
    $request = AccessRequest::create([
        'requested_by' => $requester->id,
        'external_beneficiary' => 'History Test Vendor',
    ]);
    $detail = $request->details()->create([
        'module_id' => $module->id,
        'access_type' => AccessType::VendorThirdParty->value,
        'priority' => RequestPriority::High->value,
        'justification' => 'Vendor requires temporary access.',
        'start_date' => '2026-10-01',
        'end_date' => '2026-10-31',
        'status' => AccessRequestDetailStatus::Rejected->value,
    ]);
    $detail->approvals()->create([
        'step' => ApprovalStep::Module->value,
        'approver_id' => $approver->id,
        'status' => ApprovalStatus::Rejected->value,
        'rejection_reason' => 'The vendor does not need this module.',
    ]);
    DB::table('access_request_comments')->insert([
        'request_id' => $request->id,
        'commented_by' => $approver->id,
        'comment' => 'Please confirm the vendor scope.',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $request;
}

test('authorized request viewers see complete request and approval history', function () {
    Storage::fake('local');
    $requester = makeRequestDetailViewUser('History Requester');
    $approver = makeRequestDetailViewUser('History Approver');
    $request = makeRequestDetailViewRequest($requester, $approver);
    $path = "access-requests/{$request->id}/history.pdf";
    Storage::disk('local')->put($path, 'history attachment');
    $request->attachments()->create([
        'file_name' => 'history.pdf',
        'file_path' => $path,
        'file_size' => 18,
    ]);

    $this->actingAs($requester)
        ->get(route('requests.show', $request))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('requests/view')
            ->where('accessRequest.id', $request->id)
            ->where('accessRequest.requested_by.fullname', 'History Requester')
            ->where('accessRequest.beneficiary', 'History Test Vendor')
            ->where('accessRequest.details.0.module.module_name', 'Request History Module 1')
            ->where('accessRequest.details.0.access_type', AccessType::VendorThirdParty->value)
            ->where('accessRequest.details.0.priority', RequestPriority::High->value)
            ->where('accessRequest.details.0.status', AccessRequestDetailStatus::Rejected->value)
            ->where('accessRequest.details.0.approvals.0.step', ApprovalStep::Module->value)
            ->where('accessRequest.details.0.approvals.0.status', ApprovalStatus::Rejected->value)
            ->where('accessRequest.details.0.approvals.0.approver.fullname', 'History Approver')
            ->where(
                'accessRequest.details.0.approvals.0.rejection_reason',
                'The vendor does not need this module.',
            )
            ->where('accessRequest.comments.0.comment', 'Please confirm the vendor scope.')
            ->where('accessRequest.comments.0.commented_by.fullname', 'History Approver')
            ->where('accessRequest.attachments.0.file_name', 'history.pdf')
            ->where('accessRequest.attachments.0.file_size', 18)
            ->missing('accessRequest.attachments.0.file_path')
            ->missing('accessRequest.requested_by.email')
            ->where('canCancel', false));

    $this->actingAs($approver)
        ->get(route('requests.show', $request))
        ->assertOk();
});

test('unrelated users cannot view a request or download its attachment', function () {
    Storage::fake('local');
    $requester = makeRequestDetailViewUser('Private History Requester');
    $approver = makeRequestDetailViewUser('Private History Approver');
    $unrelatedUser = makeRequestDetailViewUser('Unrelated History User');
    $request = makeRequestDetailViewRequest($requester, $approver);
    $path = "access-requests/{$request->id}/private.pdf";
    Storage::disk('local')->put($path, 'private request attachment');
    $attachment = $request->attachments()->create([
        'file_name' => 'private.pdf',
        'file_path' => $path,
        'file_size' => 27,
    ]);

    $this->actingAs($unrelatedUser)
        ->get(route('requests.show', $request))
        ->assertForbidden();

    $this->get(route('requests.attachments.download', [$request, $attachment]))
        ->assertForbidden();
});

test('authorized request viewers can download only attachments belonging to that request', function () {
    Storage::fake('local');
    $requester = makeRequestDetailViewUser('Download Requester');
    $approver = makeRequestDetailViewUser('Download Approver');
    $request = makeRequestDetailViewRequest($requester, $approver);
    $otherRequest = makeRequestDetailViewRequest(
        makeRequestDetailViewUser('Other Requester'),
        makeRequestDetailViewUser('Other Approver'),
    );
    $path = "access-requests/{$request->id}/support.pdf";
    Storage::disk('local')->put($path, 'request attachment content');
    $attachment = $request->attachments()->create([
        'file_name' => 'support.pdf',
        'file_path' => $path,
        'file_size' => 27,
    ]);
    $otherAttachment = $otherRequest->attachments()->create([
        'file_name' => 'other.pdf',
        'file_path' => $path,
        'file_size' => 27,
    ]);

    $this->actingAs($requester)
        ->get(route('requests.attachments.download', [$request, $attachment]))
        ->assertOk()
        ->assertDownload('support.pdf');

    $this->get(route('requests.attachments.download', [$request, $otherAttachment]))
        ->assertNotFound();

    $missingAttachment = $request->attachments()->create([
        'file_name' => 'missing.pdf',
        'file_path' => "access-requests/{$request->id}/missing.pdf",
        'file_size' => 1,
    ]);

    $this->get(route('requests.attachments.download', [$request, $missingAttachment]))
        ->assertNotFound();
});

test('the requester can see the cancel action while pending lines remain', function () {
    $requester = makeRequestDetailViewUser('Pending History Requester');
    $approver = makeRequestDetailViewUser('Pending History Approver');
    $request = makeRequestDetailViewRequest($requester, $approver);
    $request->details()->firstOrFail()->update([
        'status' => AccessRequestDetailStatus::Pending->value,
    ]);

    $this->actingAs($requester)
        ->get(route('requests.show', $request))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('requests/view')
            ->where('canCancel', true));
});
