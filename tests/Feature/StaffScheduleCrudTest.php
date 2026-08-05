<?php

use App\Models\Task;
use App\Models\TaskRemark;
use App\Models\User;
use App\TaskStatus;
use Backpack\CRUD\app\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

test('the app root redirects guests to registration when there are no users', function () {
    $response = $this->get('/');

    $response->assertRedirect('/register');
});

test('the app root redirects guests to login when users already exist', function () {
    User::factory()->admin()->create();

    $response = $this->get('/');

    $response->assertRedirect('/login');
});

test('an admin can create a staff account and trigger a password reset email', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin, 'backpack')->post('/staff', [
        'name' => 'New Staff Member',
        'email' => 'staff@example.com',
    ]);

    $response->assertRedirect();

    $staff = User::query()->where('email', 'staff@example.com')->first();

    expect($staff)->not->toBeNull();
    expect($staff->isStaff())->toBeTrue();

    Notification::assertSentTo($staff, ResetPasswordNotification::class);
});

test('an admin can create a single task from the default create form', function () {
    $admin = User::factory()->admin()->create();
    $staff = User::factory()->staff()->create();

    $response = $this->actingAs($admin, 'backpack')->post('/tasks', [
        'title' => 'Open shop',
        'description' => 'Be ready before customers arrive.',
        'assignee_id' => $staff->id,
        'scheduled_for' => today()->toDateString(),
        'status' => TaskStatus::Pending->value,
        'sort_order' => 1,
        'outcome_notes' => '',
    ]);

    $response->assertRedirect();

    $task = Task::query()->where('assignee_id', $staff->id)->first();

    expect($task)->not->toBeNull();
    expect($task?->title)->toBe('Open shop');
    expect($task?->admin_id)->toBe($admin->id);
    expect($task?->sort_order)->toBe(1);
});

test('an admin can assign many tasks to a staff member from the bulk create page', function () {
    $admin = User::factory()->admin()->create();
    $staff = User::factory()->staff()->create();

    $response = $this->actingAs($admin, 'backpack')->post('/tasks/bulk-create', [
        'assignee_id' => $staff->id,
        'scheduled_for' => today()->toDateString(),
        'task_lines' => "Open shop\nCheck inventory\nSend report",
    ]);

    $response->assertRedirect('/tasks');

    $tasks = Task::query()
        ->where('assignee_id', $staff->id)
        ->whereDate('scheduled_for', today())
        ->orderBy('sort_order')
        ->get();

    expect($tasks)->toHaveCount(3);
    expect($tasks->pluck('title')->all())->toBe([
        'Open shop',
        'Check inventory',
        'Send report',
    ]);
    expect($tasks->pluck('sort_order')->all())->toBe([1, 2, 3]);
});

test('the first registered user becomes an admin automatically', function () {
    config()->set('backpack.base.registration_open', true);

    $firstResponse = $this->post('/register', [
        'name' => 'First User',
        'email' => 'first@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $firstResponse->assertRedirect();

    $firstUser = User::query()->where('email', 'first@example.com')->first();

    auth('backpack')->logout();

    $secondResponse = $this->post('/register', [
        'name' => 'Second User',
        'email' => 'second@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $secondResponse->assertRedirect();

    $secondUser = User::query()->where('email', 'second@example.com')->first();

    expect($firstUser)->not->toBeNull();
    expect($secondUser)->not->toBeNull();
    expect($firstUser?->isAdmin())->toBeTrue();
    expect($secondUser->isStaff())->toBeTrue();
});

test('a staff member can update the status of an assigned task', function () {
    $admin = User::factory()->admin()->create();
    $staff = User::factory()->staff()->create();
    $task = Task::factory()->create([
        'admin_id' => $admin->id,
        'assignee_id' => $staff->id,
        'status' => TaskStatus::Pending->value,
    ]);

    $response = $this->actingAs($staff, 'backpack')->put("/tasks/{$task->id}", [
        'id' => $task->id,
        'status' => TaskStatus::Completed->value,
        'outcome_notes' => 'Task finished successfully.',
    ]);

    $response->assertRedirect();

    $task->refresh();

    expect($task->status)->toBe(TaskStatus::Completed);
    expect($task->completed_at)->not->toBeNull();
});

test('a staff member can mark a pending task as in progress from the action button route', function () {
    $admin = User::factory()->admin()->create();
    $staff = User::factory()->staff()->create();
    $task = Task::factory()->create([
        'admin_id' => $admin->id,
        'assignee_id' => $staff->id,
        'status' => TaskStatus::Pending->value,
    ]);

    $response = $this->actingAs($staff, 'backpack')->post("/tasks/{$task->id}/mark-in-progress");

    $response->assertRedirect("/tasks/{$task->id}/show");

    $task->refresh();

    expect($task->status)->toBe(TaskStatus::InProgress);
    expect($task->started_at)->not->toBeNull();
});

test('a staff member can mark an in-progress task as completed from the action button route', function () {
    $admin = User::factory()->admin()->create();
    $staff = User::factory()->staff()->create();
    $task = Task::factory()->create([
        'admin_id' => $admin->id,
        'assignee_id' => $staff->id,
        'status' => TaskStatus::InProgress->value,
        'started_at' => now()->subHour(),
    ]);

    $response = $this->actingAs($staff, 'backpack')->post("/tasks/{$task->id}/mark-completed");

    $response->assertRedirect("/tasks/{$task->id}/show");

    $task->refresh();

    expect($task->status)->toBe(TaskStatus::Completed);
    expect($task->completed_at)->not->toBeNull();
});

test('a task show page lets an authorized user reply to a remark', function () {
    $admin = User::factory()->admin()->create();
    $staff = User::factory()->staff()->create();
    $task = Task::factory()->create([
        'admin_id' => $admin->id,
        'assignee_id' => $staff->id,
    ]);
    $remark = TaskRemark::factory()->create([
        'task_id' => $task->id,
        'author_id' => $admin->id,
        'is_admin_remark' => true,
    ]);

    $showResponse = $this->actingAs($staff, 'backpack')->get("/tasks/{$task->id}/show");

    $showResponse->assertSuccessful();
    $showResponse->assertSeeText('Remarks & Responses');
    $showResponse->assertSee('Add a response');

    $replyResponse = $this->actingAs($staff, 'backpack')->post("/tasks/{$task->id}/remarks", [
        'parent_remark_id' => $remark->id,
        'body' => 'Here is my reply to this remark.',
    ]);

    $replyResponse->assertRedirect("/tasks/{$task->id}/show");

    $reply = TaskRemark::query()
        ->where('task_id', $task->id)
        ->where('parent_remark_id', $remark->id)
        ->first();

    expect($reply)->not->toBeNull();
    expect($reply?->author_id)->toBe($staff->id);
});

test('the dashboard shows todays task summary cards', function () {
    $admin = User::factory()->admin()->create();
    $staff = User::factory()->staff()->create(['name' => 'Zoe Staff']);
    $anotherStaff = User::factory()->staff()->create(['name' => 'Ada Staff']);

    $pendingTask = Task::factory()->create([
        'admin_id' => $admin->id,
        'assignee_id' => $staff->id,
        'scheduled_for' => today()->toDateString(),
        'title' => 'Open shop',
        'status' => TaskStatus::Pending->value,
    ]);
    Task::factory()->create([
        'admin_id' => $admin->id,
        'assignee_id' => $anotherStaff->id,
        'scheduled_for' => today()->toDateString(),
        'title' => 'Send report',
        'status' => TaskStatus::Completed->value,
    ]);

    $response = $this->actingAs($admin, 'backpack')->get('/dashboard');

    $response->assertSuccessful();
    $response->assertSee('Due Today');
    $response->assertSee('Tasks Due Today');
    $response->assertSee('Pending');
    $response->assertSee('Completed');
    $response->assertSee('2');
    $response->assertSee($pendingTask->title);
    $response->assertSee('Send report');
    $response->assertSee($staff->name);
    $response->assertSee($anotherStaff->name);
    $response->assertSeeInOrder([$anotherStaff->name, $staff->name]);
    $response->assertDontSee('Sort');
});

test('a staff member cannot open another staff management page', function () {
    $admin = User::factory()->admin()->create();
    $staff = User::factory()->staff()->create();

    $response = $this->actingAs($staff, 'backpack')->get("/staff/{$admin->id}/edit");

    $response->assertForbidden();
});

test('restricted sidebar tools are visible only to the configured admin email', function () {
    $admin = User::factory()->admin()->create([
        'email' => 'elvisokohasirifi@gmail.com',
    ]);
    $otherAdmin = User::factory()->admin()->create([
        'email' => 'other-admin@example.com',
    ]);
    $staff = User::factory()->staff()->create();

    $adminResponse = $this->actingAs($admin, 'backpack')->get('/dashboard');

    $adminResponse->assertSuccessful();
    $adminResponse->assertSee('Laravel Logs');
    $adminResponse->assertSee('/log');
    $adminResponse->assertSee('Backups');
    $adminResponse->assertSee('/backup');
    $adminResponse->assertSee('Activity Logs');
    $adminResponse->assertSee('/activity-log');

    $otherAdminResponse = $this->actingAs($otherAdmin, 'backpack')->get('/dashboard');

    $otherAdminResponse->assertSuccessful();
    $otherAdminResponse->assertSee('Staff');
    $otherAdminResponse->assertDontSee('Laravel Logs');
    $otherAdminResponse->assertDontSee('Backups');
    $otherAdminResponse->assertDontSee('Activity Logs');

    $staffResponse = $this->actingAs($staff, 'backpack')->get('/dashboard');

    $staffResponse->assertSuccessful();
    $staffResponse->assertDontSee('/staff');
    $staffResponse->assertDontSee('Laravel Logs');
    $staffResponse->assertDontSee('Backups');
    $staffResponse->assertDontSee('Activity Logs');
});

test('a staff member cannot edit another persons task', function () {
    $admin = User::factory()->admin()->create();
    $owner = User::factory()->staff()->create();
    $otherStaff = User::factory()->staff()->create();
    $task = Task::factory()->create([
        'admin_id' => $admin->id,
        'assignee_id' => $owner->id,
    ]);

    $response = $this->actingAs($otherStaff, 'backpack')->get("/tasks/{$task->id}/edit");

    $response->assertForbidden();
});

test('a staff members dashboard shows only their pending tasks', function () {
    $admin = User::factory()->admin()->create();
    $staff = User::factory()->staff()->create();
    $otherStaff = User::factory()->staff()->create();

    $pendingTask = Task::factory()->create([
        'admin_id' => $admin->id,
        'assignee_id' => $staff->id,
        'title' => 'Pending task for staff',
        'status' => TaskStatus::Pending->value,
    ]);

    Task::factory()->create([
        'admin_id' => $admin->id,
        'assignee_id' => $staff->id,
        'title' => 'Completed task for staff',
        'status' => TaskStatus::Completed->value,
    ]);

    Task::factory()->create([
        'admin_id' => $admin->id,
        'assignee_id' => $otherStaff->id,
        'title' => 'Another staff pending task',
        'status' => TaskStatus::Pending->value,
    ]);

    $response = $this->actingAs($staff, 'backpack')->get('/dashboard');

    $response->assertSuccessful();
    $response->assertSee('My Pending Tasks');
    $response->assertSee($pendingTask->title);
    $response->assertDontSee('Completed task for staff');
    $response->assertDontSee('Another staff pending task');
});
