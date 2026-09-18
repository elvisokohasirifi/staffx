<?php

use App\Models\Department;
use App\Models\Organization;
use App\Models\Task;
use App\Models\User;
use App\TaskStatus;
use App\Tenancy\TenantContext;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a visitor can register an organization and becomes its first admin when tenancy is enabled', function () {
    config()->set('app.is_tenant', true);

    $this->get('/admin/login')
        ->assertSuccessful()
        ->assertSee('Register your organization');

    $response = $this->post('/admin/register-organization', [
        'organization_name' => 'Grace World Church',
        'name' => 'Organization Owner',
        'email' => 'owner@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertRedirect('/admin/dashboard');

    $user = User::withoutGlobalScopes()->where('email', 'owner@example.com')->firstOrFail();
    $organization = Organization::query()->findOrFail($user->organization_id);

    expect($user->role)->toBe(UserRole::Admin)
        ->and($organization->name)->toBe('Grace World Church')
        ->and($organization->owner_id)->toBe($user->getKey());

    $this->assertAuthenticatedAs($user, 'backpack');
});

test('an organization owner can add another administrator to the organization', function () {
    config()->set('app.is_tenant', true);
    app(TenantContext::class)->clear();

    $organization = Organization::factory()->create();
    $owner = User::factory()->admin()->create(['organization_id' => $organization->id]);
    $organization->update(['owner_id' => $owner->id]);

    $response = $this->actingAs($owner, 'backpack')->post('/admin/staff', [
        'name' => 'Additional Administrator',
        'email' => 'additional-admin@example.com',
        'role' => UserRole::Admin->value,
    ]);

    $response->assertRedirect();

    $administrator = User::withoutGlobalScopes()->where('email', 'additional-admin@example.com')->firstOrFail();

    expect($administrator->role)->toBe(UserRole::Admin)
        ->and($administrator->organization_id)->toBe($organization->id);
});

test('an organization owner can assign department admins and filter the summary by department', function () {
    config()->set('app.is_tenant', true);
    app(TenantContext::class)->clear();

    $organization = Organization::factory()->create();
    $owner = User::factory()->admin()->create(['organization_id' => $organization->id]);
    $departmentAdmin = User::factory()->admin()->create(['organization_id' => $organization->id]);
    $organization->update(['owner_id' => $owner->id]);

    $departmentResponse = $this->actingAs($owner, 'backpack')->post('/admin/departments', [
        'name' => 'Operations',
        'administrators' => [$departmentAdmin->id],
    ]);

    $departmentResponse->assertRedirect();

    $department = Department::withoutGlobalScopes()->where('name', 'Operations')->firstOrFail();
    expect($department->organization_id)->toBe($organization->id);
    expect($department->administrators()->pluck('users.id')->all())->toBe([$departmentAdmin->id]);

    $operationsStaff = User::factory()->staff()->create([
        'organization_id' => $organization->id,
        'department_id' => $department->id,
        'name' => 'Operations Staff',
    ]);
    $otherStaff = User::factory()->staff()->create([
        'organization_id' => $organization->id,
        'name' => 'Other Department Staff',
    ]);

    Task::factory()->create([
        'organization_id' => $organization->id,
        'admin_id' => $owner->id,
        'assignee_id' => $operationsStaff->id,
        'title' => 'Operations task',
        'status' => TaskStatus::Pending,
    ]);
    Task::factory()->create([
        'organization_id' => $organization->id,
        'admin_id' => $owner->id,
        'assignee_id' => $otherStaff->id,
        'title' => 'Other department task',
        'status' => TaskStatus::Pending,
    ]);

    $summaryResponse = $this->actingAs($owner, 'backpack')->get("/admin/summary?department_id={$department->id}");

    $summaryResponse->assertSuccessful();
    $summaryResponse->assertSee('Viewing Operations.');
    $summaryResponse->assertSee('Operations Department Summary');
    $summaryResponse->assertSee($operationsStaff->name);
    $summaryResponse->assertDontSee($otherStaff->name);
    $summaryResponse->assertViewHas('statusCounts', fn ($statusCounts): bool => $statusCounts->firstWhere('status', TaskStatus::Pending->value)['count'] === 1);
});

test('tenant admins can bulk assign staff members to a department', function () {
    config()->set('app.is_tenant', true);
    app(TenantContext::class)->clear();

    $organization = Organization::factory()->create();
    $owner = User::factory()->admin()->create(['organization_id' => $organization->id]);
    $admin = User::factory()->admin()->create(['organization_id' => $organization->id]);
    $organization->update(['owner_id' => $owner->id]);
    $department = Department::factory()->create(['organization_id' => $organization->id]);
    $firstStaffMember = User::factory()->staff()->create(['organization_id' => $organization->id]);
    $secondStaffMember = User::factory()->staff()->create(['organization_id' => $organization->id]);

    $pageResponse = $this->actingAs($admin, 'backpack')->get('/admin/staff/bulk-assign-department');

    $pageResponse->assertSuccessful();
    $pageResponse->assertSee('Bulk Assign Department');
    $pageResponse->assertSee($firstStaffMember->name);

    $assignmentResponse = $this->actingAs($admin, 'backpack')->post('/admin/staff/bulk-assign-department', [
        'staff_ids' => [$firstStaffMember->id, $secondStaffMember->id],
        'department_id' => $department->id,
    ]);

    $assignmentResponse->assertRedirect(route('staff.bulk-assign-department'));

    expect($firstStaffMember->refresh()->department_id)->toBe($department->id)
        ->and($secondStaffMember->refresh()->department_id)->toBe($department->id);

    $otherOrganization = Organization::factory()->create();
    $otherStaffMember = User::factory()->staff()->create(['organization_id' => $otherOrganization->id]);

    $crossOrganizationResponse = $this->actingAs($admin, 'backpack')->post('/admin/staff/bulk-assign-department', [
        'staff_ids' => [$otherStaffMember->id],
        'department_id' => $department->id,
    ]);

    $crossOrganizationResponse->assertSessionHasErrors('staff_ids.0');
    expect($otherStaffMember->refresh()->department_id)->toBeNull();
});

test('tenant administrators cannot view or assign work across organizations', function () {
    config()->set('app.is_tenant', true);
    app(TenantContext::class)->clear();

    $firstOrganization = Organization::factory()->create();
    $secondOrganization = Organization::factory()->create();
    $firstAdmin = User::factory()->admin()->create(['organization_id' => $firstOrganization->id]);
    $firstStaff = User::factory()->staff()->create(['organization_id' => $firstOrganization->id]);
    $secondAdmin = User::factory()->admin()->create(['organization_id' => $secondOrganization->id]);
    $secondStaff = User::factory()->staff()->create(['organization_id' => $secondOrganization->id]);

    $firstTask = Task::factory()->create([
        'organization_id' => $firstOrganization->id,
        'admin_id' => $firstAdmin->id,
        'assignee_id' => $firstStaff->id,
        'title' => 'First organization task',
        'status' => TaskStatus::Pending,
    ]);
    $secondTask = Task::factory()->create([
        'organization_id' => $secondOrganization->id,
        'admin_id' => $secondAdmin->id,
        'assignee_id' => $secondStaff->id,
        'title' => 'Second organization task',
        'status' => TaskStatus::Pending,
    ]);

    $response = $this->actingAs($firstAdmin, 'backpack')->get('/admin/tasks');

    $response->assertSuccessful();
    $response->assertSee($firstStaff->name);
    $response->assertDontSee($secondStaff->name);

    $showResponse = $this->actingAs($firstAdmin, 'backpack')->get("/admin/tasks/{$secondTask->id}/show");

    $showResponse->assertNotFound();

    $assignmentResponse = $this->actingAs($firstAdmin, 'backpack')->post('/admin/tasks/bulk-create', [
        'scheduled_for' => today()->toDateString(),
        'scheduled_time' => '23:59',
        'task_lines' => 'Cross organization task',
        'assignee_id' => $secondStaff->id,
    ]);

    $assignmentResponse->assertSessionHasErrors('assignee_id');
    expect(Task::withoutGlobalScopes()->where('title', 'Cross organization task')->exists())->toBeFalse();
});

test('the configured admin email can view every organization and operational tools', function () {
    config()->set('app.is_tenant', true);
    config()->set('app.admin_email', 'platform-admin@example.com');
    app(TenantContext::class)->clear();

    $firstOrganization = Organization::factory()->create(['name' => 'First Organization']);
    $secondOrganization = Organization::factory()->create(['name' => 'Second Organization']);
    $platformAdmin = User::factory()->admin()->create([
        'email' => 'platform-admin@example.com',
        'organization_id' => $firstOrganization->id,
    ]);
    $organizationAdmin = User::factory()->admin()->create([
        'organization_id' => $secondOrganization->id,
    ]);

    $platformDashboard = $this->actingAs($platformAdmin, 'backpack')->get('/admin/dashboard');

    $platformDashboard->assertSuccessful();
    $platformDashboard->assertSee('Organizations');
    $platformDashboard->assertSee('Laravel Logs');
    $platformDashboard->assertSee('Activity Logs');

    $organizationsResponse = $this->actingAs($platformAdmin, 'backpack')->get('/admin/organizations');

    $organizationsResponse->assertSuccessful();

    $organizationsSearchResponse = $this->actingAs($platformAdmin, 'backpack')->post('/admin/organizations/search', [
        'draw' => 1,
        'start' => 0,
        'length' => 20,
        'search' => ['value' => '', 'regex' => 'false'],
    ]);

    $organizationsSearchResponse->assertSuccessful();
    $organizationsSearchResponse->assertSee($firstOrganization->name);
    $organizationsSearchResponse->assertSee($secondOrganization->name);

    $organizationDashboard = $this->actingAs($organizationAdmin, 'backpack')->get('/admin/dashboard');

    $organizationDashboard->assertSuccessful();
    $organizationDashboard->assertDontSee('Organizations');
    $organizationDashboard->assertDontSee('Laravel Logs');
    $organizationDashboard->assertDontSee('Activity Logs');

    $this->actingAs($organizationAdmin, 'backpack')
        ->get('/admin/organizations')
        ->assertForbidden();
});

test('the configured admin email can rename only the default organization', function () {
    config()->set('app.is_tenant', true);
    config()->set('app.admin_email', 'platform-admin@example.com');
    app(TenantContext::class)->clear();

    $defaultOrganization = Organization::factory()->create([
        'name' => 'Laravel Default Organization',
        'is_default' => true,
    ]);
    $otherOrganization = Organization::factory()->create(['name' => 'Other Organization']);
    $platformAdmin = User::factory()->admin()->create([
        'email' => 'platform-admin@example.com',
        'organization_id' => $defaultOrganization->id,
    ]);

    $this->actingAs($platformAdmin, 'backpack')
        ->get("/admin/organizations/{$defaultOrganization->id}/edit")
        ->assertSuccessful();

    $this->actingAs($platformAdmin, 'backpack')
        ->put("/admin/organizations/{$defaultOrganization->id}", [
            'id' => $defaultOrganization->id,
            'name' => 'StaffX Default Organization',
        ])
        ->assertRedirect('/admin/organizations');

    expect($defaultOrganization->refresh()->name)->toBe('StaffX Default Organization');

    $this->actingAs($platformAdmin, 'backpack')
        ->get("/admin/organizations/{$otherOrganization->id}/edit")
        ->assertForbidden();

    $this->actingAs($platformAdmin, 'backpack')
        ->put("/admin/organizations/{$otherOrganization->id}", [
            'id' => $otherOrganization->id,
            'name' => 'Renamed Other Organization',
        ])
        ->assertForbidden();

    expect($otherOrganization->refresh()->name)->toBe('Other Organization');
});

test('organization registration remains unavailable when tenancy is disabled', function () {
    config()->set('app.is_tenant', false);

    $response = $this->get('/admin/register-organization');

    $response->assertNotFound();
});
