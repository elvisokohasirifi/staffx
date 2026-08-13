<?php

use App\Jobs\RunDatabaseBackupJob;
use App\Jobs\SendPendingTaskRemindersJob;
use App\Models\EmailNotification;
use App\Models\Task;
use App\Models\TaskRemark;
use App\Models\User;
use App\Notifications\AdminEmailNotification;
use App\Notifications\PendingTasksReminderNotification;
use App\Notifications\TasksApprovedNotification;
use App\Notifications\TasksAssignedNotification;
use App\TaskStatus;
use App\UserRole;
use Backpack\CRUD\app\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Mail\SentMessage as LaravelSentMessage;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage as SymfonySentMessage;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

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

test('users can start google login from the backpack login page', function () {
    config()->set('services.google.client_id', 'google-client-id');
    config()->set('services.google.client_secret', 'google-client-secret');
    config()->set('services.google.redirect', 'http://localhost/auth/google/callback');

    Socialite::fake('google');

    $response = $this->get('/auth/google/redirect');

    $response->assertRedirect();
});

test('an existing user can sign in with google using a matching email address', function () {
    config()->set('services.google.client_id', 'google-client-id');
    config()->set('services.google.client_secret', 'google-client-secret');
    config()->set('services.google.redirect', 'http://localhost/auth/google/callback');

    $user = User::factory()->staff()->create([
        'email' => 'staff@example.com',
        'email_verified_at' => null,
        'google_id' => null,
        'google_avatar' => null,
    ]);

    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'google-user-123',
        'name' => $user->name,
        'email' => $user->email,
        'avatar' => 'https://example.com/avatar.png',
    ]));

    $response = $this->get('/auth/google/callback');

    $response->assertRedirect('/dashboard');
    $this->assertAuthenticatedAs($user->fresh(), 'backpack');

    $user->refresh();

    expect($user->google_id)->toBe('google-user-123');
    expect($user->google_avatar)->toBe('https://example.com/avatar.png');
    expect($user->email_verified_at)->not->toBeNull();
});

test('google login is rejected when there is no existing user for that email', function () {
    config()->set('services.google.client_id', 'google-client-id');
    config()->set('services.google.client_secret', 'google-client-secret');
    config()->set('services.google.redirect', 'http://localhost/auth/google/callback');

    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'google-user-999',
        'name' => 'Unknown User',
        'email' => 'unknown@example.com',
    ]));

    $response = $this->get('/auth/google/callback');

    $response->assertRedirect('/login');
    $response->assertSessionHasErrors([
        'google' => 'No account was found for this Google email. Please contact an admin.',
    ]);
    $this->assertGuest('backpack');
});

test('sent mails are also written to the application logs', function () {
    $mailLogger = Mockery::spy();
    Log::shouldReceive('channel')
        ->once()
        ->with('mail')
        ->andReturn($mailLogger);

    $email = (new Email)
        ->from(new Address('hello@example.com', 'GCI Staff'))
        ->to(new Address('staff@example.com', 'Staff User'))
        ->subject('Task reminder')
        ->text('Please complete your pending tasks.')
        ->html('<p>Please complete your pending tasks.</p>');

    Event::dispatch(new MessageSent(
        new LaravelSentMessage(
            new SymfonySentMessage(
                $email,
                new Envelope(
                    new Address('hello@example.com'),
                    [new Address('staff@example.com')]
                )
            )
        )
    ));

    $mailLogger->shouldHaveReceived('info')
        ->once()
        ->with('Mail sent', Mockery::on(function (array $context): bool {
            return $context['subject'] === 'Task reminder'
                && $context['from'] === ['"GCI Staff" <hello@example.com>']
                && $context['to'] === ['"Staff User" <staff@example.com>']
                && $context['text'] === 'Please complete your pending tasks.';
        }));
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

    $activity = Activity::query()
        ->where('subject_type', User::class)
        ->where('subject_id', $staff?->id)
        ->where('causer_type', User::class)
        ->where('causer_id', $admin->id)
        ->where('event', 'created')
        ->first();

    expect($activity)->not->toBeNull();
});

test('an admin can create an email notification for specific recipients', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $staffOne = User::factory()->staff()->create([
        'name' => 'Ada Staff',
        'email' => 'ada@example.com',
    ]);
    $staffTwo = User::factory()->staff()->create([
        'name' => 'Zoe Staff',
        'email' => 'zoe@example.com',
    ]);
    $otherAdmin = User::factory()->admin()->create([
        'name' => 'Elvis Admin',
        'email' => 'elvis@example.com',
    ]);

    $response = $this->actingAs($admin, 'backpack')->post('/email-notifications', [
        'subject' => 'Important update',
        'body' => "Please review today's schedule.\n\nReach out if you have questions.",
        'recipient_user_ids' => [$staffOne->getKey(), $otherAdmin->getKey()],
    ]);

    $response->assertRedirect();

    $emailNotification = EmailNotification::query()->first();

    expect($emailNotification)->not->toBeNull();
    expect($emailNotification?->subject)->toBe('Important update');
    expect($emailNotification?->body)->toContain("today's schedule");
    expect($emailNotification?->recipient_roles)->toBeNull();
    expect($emailNotification?->recipient_count)->toBe(2);
    expect($emailNotification?->sent_by_id)->toBe($admin->getKey());
    expect($emailNotification?->sent_at)->not->toBeNull();

    Notification::assertSentTo($staffOne, AdminEmailNotification::class, function (AdminEmailNotification $notification): bool {
        return $notification->subjectLine === 'Important update'
            && str_contains($notification->messageBody, "today's schedule");
    });
    Notification::assertSentTo($otherAdmin, AdminEmailNotification::class);
    expect(Notification::sent($staffOne, AdminEmailNotification::class))->toHaveCount(1);
    expect(Notification::sent($otherAdmin, AdminEmailNotification::class))->toHaveCount(1);
    Notification::assertNotSentTo($staffTwo, AdminEmailNotification::class);
});

test('the email notification create page includes clear controls for recipient selections', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin, 'backpack')->get('/email-notifications/create');

    $response->assertSuccessful();
    $response->assertSee('Clear role selection');
    $response->assertSee('Clear specific recipients');
    $response->assertSee('recipient_roles[]', false);
    $response->assertSee('recipient_user_ids[]', false);
});

test('email notifications require at least one resolved recipient', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin, 'backpack')->post('/email-notifications', [
        'subject' => 'Important update',
        'body' => 'Please review today\'s schedule.',
        'recipient_roles' => [],
        'recipient_user_ids' => [],
    ]);

    $response->assertSessionHasErrors('recipient_roles');
    expect(EmailNotification::query()->count())->toBe(0);
    Notification::assertNothingSent();
});

test('email notifications require either roles or specific recipients but not both', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $staff = User::factory()->staff()->create();

    $response = $this->actingAs($admin, 'backpack')->post('/email-notifications', [
        'subject' => 'Important update',
        'body' => 'Please review today\'s schedule.',
        'recipient_roles' => [UserRole::Staff->value],
        'recipient_user_ids' => [$staff->getKey()],
    ]);

    $response->assertSessionHasErrors(['recipient_roles', 'recipient_user_ids']);
    expect(EmailNotification::query()->count())->toBe(0);
    Notification::assertNothingSent();
});

test('an admin can create a single task from the default create form', function () {
    Notification::fake();

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
    expect($task?->scheduled_time)->toBe('23:59:00');

    Notification::assertSentTo($staff, TasksAssignedNotification::class, function (TasksAssignedNotification $notification): bool {
        return count($notification->tasks) === 1
            && $notification->tasks[0]['title'] === 'Open shop';
    });

    $activity = Activity::query()
        ->where('subject_type', Task::class)
        ->where('subject_id', $task?->id)
        ->where('causer_type', User::class)
        ->where('causer_id', $admin->id)
        ->where('event', 'created')
        ->first();

    expect($activity)->not->toBeNull();
});

test('scheduled time defaults to 11 59 pm when a task is created without one', function () {
    $admin = User::factory()->admin()->create();
    $staff = User::factory()->staff()->create();

    $response = $this->actingAs($admin, 'backpack')->post('/tasks', [
        'title' => 'Late follow-up',
        'description' => 'Default time test.',
        'assignee_id' => $staff->id,
        'scheduled_for' => today()->toDateString(),
        'status' => TaskStatus::Pending->value,
        'sort_order' => 1,
        'outcome_notes' => '',
    ]);

    $response->assertRedirect();

    $task = Task::query()->where('title', 'Late follow-up')->first();

    expect($task)->not->toBeNull();
    expect($task?->scheduled_time)->toBe('23:59:00');
});

test('admin personal tasks are only visible to their owner and are excluded from shared summaries', function () {
    $ownerAdmin = User::factory()->admin()->create([
        'email' => 'owner-admin@example.com',
    ]);
    $otherAdmin = User::factory()->admin()->create([
        'email' => 'other-admin@example.com',
    ]);
    $staff = User::factory()->staff()->create([
        'name' => 'Summary Staff',
        'email' => 'summary-staff@example.com',
    ]);

    $personalTask = Task::factory()->adminPersonal($ownerAdmin)->create([
        'title' => 'Owner personal task',
        'scheduled_for' => today()->toDateString(),
        'status' => TaskStatus::Completed->value,
        'approved_as_completed' => true,
    ]);

    Task::factory()->create([
        'admin_id' => $ownerAdmin->id,
        'assignee_id' => $staff->id,
        'title' => 'Shared staff task',
        'scheduled_for' => today()->toDateString(),
        'status' => TaskStatus::Pending->value,
    ]);

    $ownerPersonalTasksResponse = $this->actingAs($ownerAdmin, 'backpack')->post('/my-tasks/search', [
        'start' => 0,
        'length' => 20,
        'search' => ['value' => ''],
        'datatable_id' => 'crudTable',
    ], [
        'X-Requested-With' => 'XMLHttpRequest',
        'Accept' => 'application/json',
    ]);

    $ownerPersonalTasksResponse->assertOk();
    $ownerPersonalTasksResponse->assertSee('Owner personal task');
    $ownerPersonalTasksResponse->assertDontSee('Shared staff task');

    $ownerSharedTasksResponse = $this->actingAs($ownerAdmin, 'backpack')->post('/tasks/search', [
        'start' => 0,
        'length' => 20,
        'search' => ['value' => ''],
        'datatable_id' => 'crudTable',
    ], [
        'X-Requested-With' => 'XMLHttpRequest',
        'Accept' => 'application/json',
    ]);

    $ownerSharedTasksResponse->assertOk();
    $ownerSharedTasksResponse->assertSee('Shared staff task');
    $ownerSharedTasksResponse->assertDontSee('Owner personal task');

    $otherAdminPersonalTasksResponse = $this->actingAs($otherAdmin, 'backpack')->post('/my-tasks/search', [
        'start' => 0,
        'length' => 20,
        'search' => ['value' => ''],
        'datatable_id' => 'crudTable',
    ], [
        'X-Requested-With' => 'XMLHttpRequest',
        'Accept' => 'application/json',
    ]);

    $otherAdminPersonalTasksResponse->assertOk();
    $otherAdminPersonalTasksResponse->assertDontSee('Owner personal task');

    $otherAdminPersonalTaskShowResponse = $this->actingAs($otherAdmin, 'backpack')->get("/my-tasks/{$personalTask->id}/show");

    $otherAdminPersonalTaskShowResponse->assertNotFound();

    $summaryResponse = $this->actingAs($ownerAdmin, 'backpack')->get('/summary');

    $summaryResponse->assertSuccessful();
    $summaryResponse->assertViewHas('staffSummaries', function ($staffSummaries) use ($staff): bool {
        $summary = collect($staffSummaries)->first(fn (array $item): bool => $item['staff']->is($staff));

        return $summary !== null
            && $summary['assigned_count'] === 1
            && $summary['completed_count'] === 0
            && $summary['pending_count'] === 1
            && $summary['could_not_be_achieved_count'] === 0;
    });
    $summaryResponse->assertViewHas('statusCounts', function ($statusCounts): bool {
        return collect($statusCounts)->contains(fn (array $item): bool => $item['status'] === TaskStatus::Pending->value && $item['count'] === 1)
            && collect($statusCounts)->contains(fn (array $item): bool => $item['status'] === TaskStatus::Completed->value && $item['count'] === 0);
    });
});

test('an admin can bulk create private personal tasks from the my tasks section', function () {
    $admin = User::factory()->admin()->create([
        'email' => 'personal-bulk-admin@example.com',
    ]);

    $response = $this->actingAs($admin, 'backpack')->post('/my-tasks/bulk-create', [
        'scheduled_for' => today()->toDateString(),
        'task_lines' => "Review branch reports\nPrepare tomorrow outline\nFollow up on approvals",
    ]);

    $response->assertRedirect('/my-tasks');

    $tasks = Task::query()
        ->adminPersonalTasks()
        ->where('admin_id', $admin->id)
        ->where('assignee_id', $admin->id)
        ->whereDate('scheduled_for', today())
        ->orderBy('sort_order')
        ->get();

    expect($tasks)->toHaveCount(3);
    expect($tasks->pluck('title')->all())->toBe([
        'Review branch reports',
        'Prepare tomorrow outline',
        'Follow up on approvals',
    ]);
    expect($tasks->pluck('sort_order')->all())->toBe([1, 2, 3]);
    expect($tasks->every(fn (Task $task): bool => $task->is_admin_personal))->toBeTrue();

    $tasksPageResponse = $this->actingAs($admin, 'backpack')->get('/my-tasks');

    $tasksPageResponse->assertSuccessful();
    $tasksPageResponse->assertSee('Bulk Create My Tasks');
});

test('the my tasks list shows filters and applies them to personal task search results', function () {
    $admin = User::factory()->admin()->create([
        'email' => 'filtered-personal-admin@example.com',
    ]);
    $otherAdmin = User::factory()->admin()->create([
        'email' => 'other-filtered-personal-admin@example.com',
    ]);

    $includedTask = Task::factory()->adminPersonal($admin)->create([
        'title' => 'Included personal completed task',
        'scheduled_for' => '2026-08-12',
        'status' => TaskStatus::Completed->value,
    ]);
    Task::factory()->adminPersonal($admin)->create([
        'title' => 'Excluded personal pending task',
        'scheduled_for' => '2026-08-13',
        'status' => TaskStatus::Pending->value,
    ]);
    Task::factory()->adminPersonal($otherAdmin)->create([
        'title' => 'Other admin personal completed task',
        'scheduled_for' => '2026-08-12',
        'status' => TaskStatus::Completed->value,
    ]);

    $response = $this->actingAs($admin, 'backpack')->get('/my-tasks?start_date=2026-08-12&end_date=2026-08-12&status=completed');

    $response->assertSuccessful();
    $response->assertSee('Filters');
    $response->assertSee('Start Date');
    $response->assertSee('End Date');
    $response->assertSee('Status');
    $response->assertSee('Apply Filter');
    $response->assertSee('value="2026-08-12"', false);
    $response->assertSee('value="completed" selected', false);

    $searchResponse = $this->actingAs($admin, 'backpack')->post('/my-tasks/search?start_date=2026-08-12&end_date=2026-08-12&status=completed', [
        'start' => 0,
        'length' => 20,
        'search' => ['value' => ''],
        'datatable_id' => 'crudTable',
    ], [
        'X-Requested-With' => 'XMLHttpRequest',
        'Accept' => 'application/json',
    ]);

    $searchResponse->assertOk();
    $searchResponse->assertSee($includedTask->title);
    $searchResponse->assertDontSee('Excluded personal pending task');
    $searchResponse->assertDontSee('Other admin personal completed task');
});

test('the my task show page includes the delete action script', function () {
    $admin = User::factory()->admin()->create([
        'email' => 'personal-delete-admin@example.com',
    ]);

    $task = Task::factory()->adminPersonal($admin)->create([
        'title' => 'Delete-ready personal task',
    ]);

    $response = $this->actingAs($admin, 'backpack')->get("/my-tasks/{$task->id}/show");

    $response->assertSuccessful();
    $response->assertSee('onclick="deleteEntry(this)"', false);
    $response->assertSee('function deleteEntry(button)', false);
});

test('the my tasks list search shows progress buttons for personal tasks that can be started or completed', function () {
    $admin = User::factory()->admin()->create([
        'email' => 'personal-progress-buttons@example.com',
    ]);

    Task::factory()->adminPersonal($admin)->create([
        'title' => 'Pending personal task',
        'status' => TaskStatus::Pending->value,
    ]);
    Task::factory()->adminPersonal($admin)->create([
        'title' => 'In progress personal task',
        'status' => TaskStatus::InProgress->value,
        'started_at' => now()->subHour(),
    ]);

    $response = $this->actingAs($admin, 'backpack')->post('/my-tasks/search', [
        'start' => 0,
        'length' => 20,
        'search' => ['value' => ''],
        'datatable_id' => 'crudTable',
    ], [
        'X-Requested-With' => 'XMLHttpRequest',
        'Accept' => 'application/json',
    ]);

    $response->assertOk();
    $response->assertSee('mark-in-progress');
    $response->assertSee('mark-completed');
    $response->assertSee('Start');
    $response->assertSee('Complete');
});

test('an admin can mark a pending personal task as in progress from my tasks', function () {
    $admin = User::factory()->admin()->create([
        'email' => 'personal-progress-start@example.com',
    ]);
    $task = Task::factory()->adminPersonal($admin)->create([
        'status' => TaskStatus::Pending->value,
    ]);

    $response = $this->actingAs($admin, 'backpack')->post("/my-tasks/{$task->id}/mark-in-progress");

    $response->assertRedirect("/my-tasks/{$task->id}/show");

    $task->refresh();

    expect($task->status)->toBe(TaskStatus::InProgress);
    expect($task->started_at)->not->toBeNull();
});

test('an admin can mark an in-progress personal task as completed from my tasks', function () {
    $admin = User::factory()->admin()->create([
        'email' => 'personal-progress-complete@example.com',
    ]);
    $task = Task::factory()->adminPersonal($admin)->create([
        'status' => TaskStatus::InProgress->value,
        'started_at' => now()->subMinutes(30),
    ]);

    $response = $this->actingAs($admin, 'backpack')->post("/my-tasks/{$task->id}/mark-completed");

    $response->assertRedirect("/my-tasks/{$task->id}/show");

    $task->refresh();

    expect($task->status)->toBe(TaskStatus::Completed);
    expect($task->completed_at)->not->toBeNull();
});

test('the my task show page includes personal task action buttons when the task is still open', function () {
    $admin = User::factory()->admin()->create([
        'email' => 'personal-show-actions@example.com',
    ]);
    $task = Task::factory()->adminPersonal($admin)->create([
        'status' => TaskStatus::Pending->value,
    ]);

    $response = $this->actingAs($admin, 'backpack')->get("/my-tasks/{$task->id}/show");

    $response->assertSuccessful();
    $response->assertSeeText('Task Actions');
    $response->assertSee('Mark as In Progress');
});

test('the my tasks page shows all-time personal task cards and status distribution', function () {
    $admin = User::factory()->admin()->create([
        'email' => 'personal-overview-admin@example.com',
    ]);
    $otherAdmin = User::factory()->admin()->create([
        'email' => 'other-personal-overview-admin@example.com',
    ]);

    Task::factory()->adminPersonal($admin)->create([
        'title' => 'Pending personal task',
        'status' => TaskStatus::Pending->value,
    ]);
    Task::factory()->adminPersonal($admin)->create([
        'title' => 'In-progress personal task',
        'status' => TaskStatus::InProgress->value,
    ]);
    Task::factory()->adminPersonal($admin)->create([
        'title' => 'Awaiting approval personal task',
        'status' => TaskStatus::Completed->value,
        'approved_as_completed' => false,
    ]);
    Task::factory()->adminPersonal($admin)->create([
        'title' => 'Approved personal task',
        'status' => TaskStatus::Completed->value,
        'approved_as_completed' => true,
    ]);
    Task::factory()->adminPersonal($admin)->create([
        'title' => 'Blocked personal task',
        'status' => TaskStatus::CouldNotBeAchieved->value,
    ]);
    Task::factory()->adminPersonal($otherAdmin)->create([
        'title' => 'Other admin personal task',
        'status' => TaskStatus::Completed->value,
        'approved_as_completed' => true,
    ]);

    $response = $this->actingAs($admin, 'backpack')->get('/my-tasks');

    $response->assertSuccessful();
    $response->assertSee('Total Personal Tasks');
    $response->assertSee('Pending / In Progress');
    $response->assertSee('Approved Completed');
    $response->assertSee('Could Not Be Completed');
    $response->assertSee('Task Status Distribution (All Time)');
    $response->assertSee('20.0%');
    $response->assertViewHas('myTaskDashboardStats', function (array $stats): bool {
        return $stats['total_tasks'] === 5
            && $stats['open_tasks'] === 2
            && $stats['completed_status'] === 2
            && $stats['completed'] === 1
            && $stats['could_not_be_achieved'] === 1
            && $stats['completion_rate'] === 20.0;
    });
    $response->assertViewHas('myTaskStatusCounts', function ($statusCounts): bool {
        return collect($statusCounts)->contains(fn (array $item): bool => $item['status'] === TaskStatus::Pending->value && $item['count'] === 2)
            && collect($statusCounts)->contains(fn (array $item): bool => $item['status'] === TaskStatus::InProgress->value && $item['count'] === 1)
            && collect($statusCounts)->contains(fn (array $item): bool => $item['status'] === TaskStatus::Completed->value && $item['count'] === 1)
            && collect($statusCounts)->contains(fn (array $item): bool => $item['status'] === TaskStatus::CouldNotBeAchieved->value && $item['count'] === 1);
    });
});

test('the my tasks list page includes the delete action script', function () {
    $admin = User::factory()->admin()->create([
        'email' => 'personal-list-delete-admin@example.com',
    ]);

    Task::factory()->adminPersonal($admin)->create([
        'title' => 'List delete-ready personal task',
    ]);

    $response = $this->actingAs($admin, 'backpack')->get('/my-tasks');

    $response->assertSuccessful();
    $response->assertSee('function deleteEntry(button)', false);
});

test('an admin can open the my tasks bulk update page and update selected personal tasks', function () {
    $admin = User::factory()->admin()->create([
        'email' => 'personal-bulk-update-admin@example.com',
    ]);

    $firstTask = Task::factory()->adminPersonal($admin)->create([
        'title' => 'First personal bulk update task',
        'scheduled_for' => today()->toDateString(),
        'status' => TaskStatus::Pending->value,
        'approved_as_completed' => false,
    ]);
    $secondTask = Task::factory()->adminPersonal($admin)->create([
        'title' => 'Second personal bulk update task',
        'scheduled_for' => today()->toDateString(),
        'status' => TaskStatus::Pending->value,
        'approved_as_completed' => false,
    ]);

    $pageResponse = $this->actingAs($admin, 'backpack')->get('/my-tasks/bulk-update');

    $pageResponse->assertSuccessful();
    $pageResponse->assertSee('Bulk Update My Tasks');
    $pageResponse->assertSee($firstTask->title);
    $pageResponse->assertSee($secondTask->title);

    $updateResponse = $this->actingAs($admin, 'backpack')->post('/my-tasks/bulk-update', [
        'task_ids' => [$firstTask->getKey(), $secondTask->getKey()],
        'scheduled_for' => today()->addDay()->toDateString(),
        'status' => TaskStatus::Completed->value,
        'approval_action' => 'approve',
        'filter_scheduled_for' => today()->toDateString(),
    ]);

    $updateResponse->assertRedirect('/my-tasks/bulk-update?filter_scheduled_for='.today()->toDateString());

    $firstTask->refresh();
    $secondTask->refresh();

    expect($firstTask->status)->toBe(TaskStatus::Completed);
    expect($secondTask->status)->toBe(TaskStatus::Completed);
    expect($firstTask->approved_as_completed)->toBeTrue();
    expect($secondTask->approved_as_completed)->toBeTrue();
    expect($firstTask->scheduled_for?->toDateString())->toBe(today()->addDay()->toDateString());
    expect($secondTask->scheduled_for?->toDateString())->toBe(today()->addDay()->toDateString());
});

test('an admin can open the my tasks bulk delete page and delete selected personal tasks', function () {
    $admin = User::factory()->admin()->create([
        'email' => 'personal-bulk-delete-admin@example.com',
    ]);

    $firstTask = Task::factory()->adminPersonal($admin)->create([
        'title' => 'First personal bulk delete task',
        'scheduled_for' => today()->toDateString(),
    ]);
    $secondTask = Task::factory()->adminPersonal($admin)->create([
        'title' => 'Second personal bulk delete task',
        'scheduled_for' => today()->toDateString(),
    ]);

    $pageResponse = $this->actingAs($admin, 'backpack')->get('/my-tasks/bulk-delete');

    $pageResponse->assertSuccessful();
    $pageResponse->assertSee('Bulk Delete My Tasks');
    $pageResponse->assertSee($firstTask->title);
    $pageResponse->assertSee($secondTask->title);

    $deleteResponse = $this->actingAs($admin, 'backpack')->post('/my-tasks/bulk-delete', [
        'task_ids' => [$firstTask->getKey(), $secondTask->getKey()],
        'filter_scheduled_for' => today()->toDateString(),
    ]);

    $deleteResponse->assertRedirect('/my-tasks/bulk-delete?filter_scheduled_for='.today()->toDateString());

    expect(Task::query()->whereKey($firstTask->getKey())->exists())->toBeFalse();
    expect(Task::query()->whereKey($secondTask->getKey())->exists())->toBeFalse();
});

test('an admin can assign many tasks to a staff member from the bulk create page', function () {
    Notification::fake();

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

    Notification::assertSentTo($staff, TasksAssignedNotification::class, function (TasksAssignedNotification $notification): bool {
        return count($notification->tasks) === 3
            && collect($notification->tasks)->pluck('title')->all() === [
                'Open shop',
                'Check inventory',
                'Send report',
            ];
    });

    $tasksPageResponse = $this->actingAs($admin, 'backpack')->get('/tasks');

    $tasksPageResponse->assertSuccessful();
    $tasksPageResponse->assertSee('Bulk Create Tasks');
    $tasksPageResponse->assertSee('class="btn btn-outline-primary"', false);
    $tasksPageResponse->assertDontSee('class="btn btn-sm btn-outline-primary"', false);
});

test('an admin can open the bulk update page from tasks', function () {
    $admin = User::factory()->admin()->create();

    $tasksPageResponse = $this->actingAs($admin, 'backpack')->get('/tasks');

    $tasksPageResponse->assertSuccessful();
    $tasksPageResponse->assertSee('Bulk Update Tasks');

    $bulkUpdateResponse = $this->actingAs($admin, 'backpack')->get('/tasks/bulk-update');

    $bulkUpdateResponse->assertSuccessful();
    $bulkUpdateResponse->assertSee('Bulk Update Tasks');
    $bulkUpdateResponse->assertSee('Matching Tasks');
    $bulkUpdateResponse->assertSee('Updates To Apply');
});

test('an admin can open the bulk delete page and delete selected tasks', function () {
    $admin = User::factory()->admin()->create();
    $staff = User::factory()->staff()->create();
    $firstTask = Task::factory()->create([
        'admin_id' => $admin->id,
        'assignee_id' => $staff->id,
        'title' => 'Delete me first',
        'scheduled_for' => today()->toDateString(),
    ]);
    $secondTask = Task::factory()->create([
        'admin_id' => $admin->id,
        'assignee_id' => $staff->id,
        'title' => 'Delete me second',
        'scheduled_for' => today()->toDateString(),
    ]);

    $tasksPageResponse = $this->actingAs($admin, 'backpack')->get('/tasks');

    $tasksPageResponse->assertSuccessful();
    $tasksPageResponse->assertSee('Bulk Delete Tasks');

    $bulkDeleteResponse = $this->actingAs($admin, 'backpack')->get('/tasks/bulk-delete');

    $bulkDeleteResponse->assertSuccessful();
    $bulkDeleteResponse->assertSee('Bulk Delete Tasks');
    $bulkDeleteResponse->assertSee($firstTask->title);
    $bulkDeleteResponse->assertSee($secondTask->title);

    $deleteResponse = $this->actingAs($admin, 'backpack')->post('/tasks/bulk-delete', [
        'task_ids' => [$firstTask->id, $secondTask->id],
        'filter_scheduled_for' => today()->toDateString(),
    ]);

    $deleteResponse->assertRedirect('/tasks/bulk-delete?filter_scheduled_for='.today()->toDateString());

    expect(Task::query()->whereKey($firstTask->id)->exists())->toBeFalse();
    expect(Task::query()->whereKey($secondTask->id)->exists())->toBeFalse();
});

test('an admin can bulk reassign tasks and staff receives one grouped assignment notification', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $currentStaff = User::factory()->staff()->create();
    $newStaff = User::factory()->staff()->create(['email' => 'new-staff@example.com']);

    $firstTask = Task::factory()->create([
        'admin_id' => $admin->id,
        'assignee_id' => $currentStaff->id,
        'scheduled_for' => today()->toDateString(),
        'title' => 'First reassigned task',
    ]);
    $secondTask = Task::factory()->create([
        'admin_id' => $admin->id,
        'assignee_id' => $currentStaff->id,
        'scheduled_for' => today()->toDateString(),
        'title' => 'Second reassigned task',
    ]);

    $response = $this->actingAs($admin, 'backpack')->post('/tasks/bulk-update', [
        'task_ids' => [$firstTask->id, $secondTask->id],
        'assignee_id' => $newStaff->id,
        'scheduled_for' => '',
        'status' => '',
        'approval_action' => '',
        'filter_scheduled_for' => today()->toDateString(),
    ]);

    $response->assertRedirect('/tasks/bulk-update?filter_scheduled_for='.today()->toDateString());

    $firstTask->refresh();
    $secondTask->refresh();

    expect($firstTask->assignee_id)->toBe($newStaff->id);
    expect($secondTask->assignee_id)->toBe($newStaff->id);

    Notification::assertSentTo($newStaff, TasksAssignedNotification::class, function (TasksAssignedNotification $notification) use ($firstTask, $secondTask): bool {
        return count($notification->tasks) === 2
            && collect($notification->tasks)->pluck('title')->all() === [
                $firstTask->title,
                $secondTask->title,
            ];
    });
});

test('an admin can bulk approve completed tasks and staff receives one grouped approval notification', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $staff = User::factory()->staff()->create();

    $firstTask = Task::factory()->create([
        'admin_id' => $admin->id,
        'assignee_id' => $staff->id,
        'status' => TaskStatus::Completed->value,
        'approved_as_completed' => false,
        'title' => 'First bulk approved task',
    ]);
    $secondTask = Task::factory()->create([
        'admin_id' => $admin->id,
        'assignee_id' => $staff->id,
        'status' => TaskStatus::Completed->value,
        'approved_as_completed' => false,
        'title' => 'Second bulk approved task',
    ]);

    $response = $this->actingAs($admin, 'backpack')->post('/tasks/bulk-update', [
        'task_ids' => [$firstTask->id, $secondTask->id],
        'assignee_id' => '',
        'scheduled_for' => '',
        'status' => '',
        'approval_action' => 'approve',
    ]);

    $response->assertRedirect('/tasks/bulk-update');

    $firstTask->refresh();
    $secondTask->refresh();

    expect($firstTask->approved_as_completed)->toBeTrue();
    expect($secondTask->approved_as_completed)->toBeTrue();

    Notification::assertSentTo($staff, TasksApprovedNotification::class, function (TasksApprovedNotification $notification) use ($firstTask, $secondTask): bool {
        return count($notification->tasks) === 2
            && collect($notification->tasks)->pluck('title')->all() === [
                $firstTask->title,
                $secondTask->title,
            ];
    });
});

test('the database backup job runs the backup plugin for database backups only', function () {
    Artisan::shouldReceive('call')
        ->once()
        ->with('backup:run', [
            '--only-db' => true,
            '--disable-notifications' => true,
        ])
        ->andReturn(0);

    app(RunDatabaseBackupJob::class)->handle();
});

test('the pending task reminders job emails each staff member their incomplete tasks for today', function () {
    Notification::fake();

    $staff = User::factory()->staff()->create();
    $otherStaff = User::factory()->staff()->create();

    Task::factory()->create([
        'assignee_id' => $staff->id,
        'title' => 'Pending reminder task',
        'scheduled_for' => today()->toDateString(),
        'status' => TaskStatus::Pending->value,
    ]);
    Task::factory()->create([
        'assignee_id' => $staff->id,
        'title' => 'Awaiting approval reminder task',
        'scheduled_for' => today()->toDateString(),
        'status' => TaskStatus::Completed->value,
        'approved_as_completed' => false,
    ]);
    Task::factory()->create([
        'assignee_id' => $staff->id,
        'title' => 'Approved completed task',
        'scheduled_for' => today()->toDateString(),
        'status' => TaskStatus::Completed->value,
        'approved_as_completed' => true,
    ]);
    Task::factory()->create([
        'assignee_id' => $otherStaff->id,
        'title' => 'Other staff blocked task',
        'scheduled_for' => today()->toDateString(),
        'status' => TaskStatus::CouldNotBeAchieved->value,
    ]);
    Task::factory()->create([
        'assignee_id' => $staff->id,
        'title' => 'Tomorrow task',
        'scheduled_for' => today()->addDay()->toDateString(),
        'status' => TaskStatus::Pending->value,
    ]);

    app(SendPendingTaskRemindersJob::class)->handle();

    Notification::assertSentTo($staff, PendingTasksReminderNotification::class, function (PendingTasksReminderNotification $notification): bool {
        return count($notification->tasks) === 2
            && collect($notification->tasks)->pluck('title')->all() === [
                'Pending reminder task',
                'Awaiting approval reminder task',
            ];
    });

    Notification::assertSentTo($otherStaff, PendingTasksReminderNotification::class, function (PendingTasksReminderNotification $notification): bool {
        return count($notification->tasks) === 1
            && $notification->tasks[0]['title'] === 'Other staff blocked task';
    });
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

test('a staff member can mark a pending task as completed from the completion route', function () {
    $admin = User::factory()->admin()->create();
    $staff = User::factory()->staff()->create();
    $task = Task::factory()->create([
        'admin_id' => $admin->id,
        'assignee_id' => $staff->id,
        'status' => TaskStatus::Pending->value,
    ]);

    $response = $this->actingAs($staff, 'backpack')->post("/tasks/{$task->id}/mark-completed");

    $response->assertRedirect("/tasks/{$task->id}/show");

    $task->refresh();

    expect($task->status)->toBe(TaskStatus::Completed);
    expect($task->completed_at)->not->toBeNull();
});

test('a stale in-progress request redirects instead of failing for staff', function () {
    $admin = User::factory()->admin()->create();
    $staff = User::factory()->staff()->create();
    $task = Task::factory()->create([
        'admin_id' => $admin->id,
        'assignee_id' => $staff->id,
        'status' => TaskStatus::InProgress->value,
        'started_at' => now()->subMinutes(15),
    ]);

    $response = $this->actingAs($staff, 'backpack')->post("/tasks/{$task->id}/mark-in-progress");

    $response->assertRedirect("/tasks/{$task->id}/show");

    $task->refresh();

    expect($task->status)->toBe(TaskStatus::InProgress);
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
        'approved_as_completed' => true,
    ]);

    $response = $this->actingAs($admin, 'backpack')->get('/dashboard');

    $response->assertSuccessful();
    $response->assertSee('Due Today');
    $response->assertSee('Tasks Due Today');
    $response->assertSee('Pending');
    $response->assertSee('Completed');
    $response->assertSee('2');
    $response->assertSee('mobile-table-scroll');
    $response->assertSee('min-width: 720px;', false);
    $response->assertSee($pendingTask->title);
    $response->assertSee('Send report');
    $response->assertSee($staff->name);
    $response->assertSee($anotherStaff->name);
    $response->assertSeeInOrder([$anotherStaff->name, $staff->name]);
    $response->assertDontSee('Sort');
});

test('dashboard counts only approved completed tasks as completed', function () {
    $admin = User::factory()->admin()->create();
    $staff = User::factory()->staff()->create();

    Task::factory()->create([
        'admin_id' => $admin->id,
        'assignee_id' => $staff->id,
        'title' => 'Awaiting approval task',
        'scheduled_for' => today()->toDateString(),
        'status' => TaskStatus::Completed->value,
        'approved_as_completed' => false,
    ]);
    Task::factory()->create([
        'admin_id' => $admin->id,
        'assignee_id' => $staff->id,
        'title' => 'Approved task',
        'scheduled_for' => today()->toDateString(),
        'status' => TaskStatus::Completed->value,
        'approved_as_completed' => true,
    ]);

    $response = $this->actingAs($admin, 'backpack')->get('/dashboard');

    $response->assertSuccessful();
    $response->assertSee('Awaiting approval task');
    $response->assertSee('Approved task');
    $response->assertSeeInOrder(['Pending', '1']);
    $response->assertSeeInOrder(['Completed', '1']);
});

test('an admin can open a staff members tasks from the staff list', function () {
    $admin = User::factory()->admin()->create();
    $staff = User::factory()->staff()->create();

    $response = $this->actingAs($admin, 'backpack')->post('/staff/search', [
        'draw' => 1,
        'start' => 0,
        'length' => 20,
        'search' => ['value' => '', 'regex' => 'false'],
    ]);

    $response->assertSuccessful();
    $response->assertSee('View Tasks');
    $response->assertSee('/tasks?staff_id='.$staff->id, false);
});

test('the task show page displays the full title', function () {
    $admin = User::factory()->admin()->create();
    $staff = User::factory()->staff()->create();
    $title = 'Send a performance analysis report and send low-performing churches for Query Meeting/Send a list of query meetings for all defaulting PPCs under Zones (Appraisal Template, Finances, all cell defaulters) (When Needed)';

    $task = Task::factory()->create([
        'admin_id' => $admin->id,
        'assignee_id' => $staff->id,
        'title' => $title,
    ]);

    $response = $this->actingAs($admin, 'backpack')->get("/tasks/{$task->id}/show");

    $response->assertSuccessful();
    $response->assertSeeText($title);
});

test('a staff member cannot open another staff management page', function () {
    $admin = User::factory()->admin()->create();
    $staff = User::factory()->staff()->create();

    $response = $this->actingAs($staff, 'backpack')->get("/staff/{$admin->id}/edit");

    $response->assertForbidden();
});

test('the configured admin email can see and update all users including admins', function () {
    config()->set('app.admin_email', 'owner@example.com');

    $ownerAdmin = User::factory()->admin()->create([
        'email' => 'owner@example.com',
    ]);
    $otherAdmin = User::factory()->admin()->create([
        'name' => 'Other Admin',
        'email' => 'other-admin@example.com',
    ]);
    $staff = User::factory()->staff()->create([
        'name' => 'Listed Staff',
        'email' => 'listed-staff@example.com',
    ]);

    $listResponse = $this->actingAs($ownerAdmin, 'backpack')->post('/staff/search', [
        'draw' => 1,
        'start' => 0,
        'length' => 20,
        'search' => ['value' => '', 'regex' => 'false'],
    ]);

    $listResponse->assertSuccessful();
    $listResponse->assertSee($otherAdmin->email);
    $listResponse->assertSee($staff->email);
    $listResponse->assertSee('Admin');
    $listResponse->assertSee('Staff');
    $listResponse->assertDontSee('/tasks?staff_id='.$otherAdmin->id, false);
    $listResponse->assertSee('/tasks?staff_id='.$staff->id, false);

    $editResponse = $this->actingAs($ownerAdmin, 'backpack')->get("/staff/{$otherAdmin->id}/edit");

    $editResponse->assertSuccessful();
    $editResponse->assertSee('name="role"', false);
    $editResponse->assertSee('name="password"', false);

    $updateResponse = $this->actingAs($ownerAdmin, 'backpack')->put("/staff/{$otherAdmin->id}", [
        'id' => $otherAdmin->id,
        'name' => 'Updated Admin Name',
        'email' => 'updated-admin@example.com',
        'password' => 'new-password-123',
        'role' => UserRole::Staff->value,
    ]);

    $updateResponse->assertRedirect();

    $otherAdmin->refresh();

    expect($otherAdmin->name)->toBe('Updated Admin Name');
    expect($otherAdmin->email)->toBe('updated-admin@example.com');
    expect($otherAdmin->isStaff())->toBeTrue();
    expect(Hash::check('new-password-123', $otherAdmin->password))->toBeTrue();
});

test('regular admins still only manage staff users', function () {
    config()->set('app.admin_email', 'owner@example.com');

    $ownerAdmin = User::factory()->admin()->create([
        'email' => 'owner@example.com',
    ]);
    $regularAdmin = User::factory()->admin()->create([
        'email' => 'regular-admin@example.com',
    ]);
    $staff = User::factory()->staff()->create();

    $listResponse = $this->actingAs($regularAdmin, 'backpack')->post('/staff/search', [
        'draw' => 1,
        'start' => 0,
        'length' => 20,
        'search' => ['value' => '', 'regex' => 'false'],
    ]);

    $listResponse->assertSuccessful();
    $listResponse->assertSee($staff->email);
    $listResponse->assertDontSee($ownerAdmin->email);

    $editResponse = $this->actingAs($regularAdmin, 'backpack')->get("/staff/{$ownerAdmin->id}/edit");

    $editResponse->assertForbidden();
});

test('restricted sidebar tools are visible only to the configured admin email', function () {
    config()->set('app.admin_email', 'elvisokohasirifi@gmail.com');

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

test('activity buttons are visible only to the configured admin email', function () {
    config()->set('app.admin_email', 'elvisokohasirifi@gmail.com');

    $allowedAdmin = User::factory()->admin()->create([
        'email' => 'elvisokohasirifi@gmail.com',
    ]);
    $otherAdmin = User::factory()->admin()->create([
        'email' => 'other-admin@example.com',
    ]);
    $staff = User::factory()->staff()->create();

    Task::factory()->create([
        'admin_id' => $allowedAdmin->id,
        'assignee_id' => $staff->id,
    ]);

    $allowedStaffPage = $this->actingAs($allowedAdmin, 'backpack')->get('/staff');
    $allowedStaffPage->assertSuccessful();
    $allowedStaffPage->assertSee('activity-log-model');

    $allowedTasksPage = $this->actingAs($allowedAdmin, 'backpack')->get('/tasks');
    $allowedTasksPage->assertSuccessful();
    $allowedTasksPage->assertSee('activity-log-model');

    $otherStaffPage = $this->actingAs($otherAdmin, 'backpack')->get('/staff');
    $otherStaffPage->assertSuccessful();
    $otherStaffPage->assertDontSee('activity-log-model');

    $otherTasksPage = $this->actingAs($otherAdmin, 'backpack')->get('/tasks');
    $otherTasksPage->assertSuccessful();
    $otherTasksPage->assertDontSee('activity-log-model');
});

test('an admin can impersonate another user and return to their own account', function () {
    $admin = User::factory()->admin()->create([
        'email' => 'other-admin@example.com',
    ]);
    $staff = User::factory()->staff()->create([
        'name' => 'Target Staff',
        'email' => 'target@example.com',
    ]);

    $impersonateResponse = $this->actingAs($admin, 'backpack')->post("/staff/{$staff->id}/impersonate");

    $impersonateResponse->assertRedirect('/dashboard');
    expect(auth('backpack')->user()?->is($staff))->toBeTrue();
    expect(session('impersonator_id'))->toBe($admin->id);

    $dashboardResponse = $this->actingAs($staff, 'backpack')->get('/dashboard');
    $dashboardResponse->assertSuccessful();
    $dashboardResponse->assertSee('Stop Impersonating');

    $stopResponse = $this->actingAs($staff, 'backpack')->post('/stop-impersonating');

    $stopResponse->assertRedirect('/dashboard');
    expect(auth('backpack')->user()?->is($admin))->toBeTrue();
    expect(session()->has('impersonator_id'))->toBeFalse();
});

test('staff users cannot impersonate other users', function () {
    $staffUser = User::factory()->staff()->create();
    $staff = User::factory()->staff()->create();

    $response = $this->actingAs($staffUser, 'backpack')->post("/staff/{$staff->id}/impersonate");

    $response->assertForbidden();
});

test('the task list shows a scheduled date range filter and applies it to task search results', function () {
    $admin = User::factory()->admin()->create();
    $staff = User::factory()->staff()->create();

    $includedTask = Task::factory()->create([
        'admin_id' => $admin->id,
        'assignee_id' => $staff->id,
        'title' => 'Included August task',
        'scheduled_for' => '2026-08-10',
    ]);
    $excludedTask = Task::factory()->create([
        'admin_id' => $admin->id,
        'assignee_id' => $staff->id,
        'title' => 'Excluded September task',
        'scheduled_for' => '2026-09-10',
    ]);

    $response = $this->actingAs($admin, 'backpack')->get('/tasks?start_date=2026-08-01&end_date=2026-08-31');

    $response->assertSuccessful();
    $response->assertSee('Start Date');
    $response->assertSee('End Date');
    $response->assertSee('value="2026-08-01"', false);
    $response->assertSee('value="2026-08-31"', false);

    $searchResponse = $this->actingAs($admin, 'backpack')->post('/tasks/search?start_date=2026-08-01&end_date=2026-08-31', [
        'start' => 0,
        'length' => 20,
        'search' => ['value' => ''],
        'datatable_id' => 'crudTable',
    ], [
        'X-Requested-With' => 'XMLHttpRequest',
        'Accept' => 'application/json',
    ]);

    $searchResponse->assertOk();
    $searchResponse->assertSee($includedTask->title);
    $searchResponse->assertDontSee($excludedTask->title);
});

test('the task list shows an admin-only staff filter and applies it to task search results', function () {
    $admin = User::factory()->admin()->create();
    $staffOne = User::factory()->staff()->create(['name' => 'Ada Staff']);
    $staffTwo = User::factory()->staff()->create(['name' => 'Zoe Staff']);

    $includedTask = Task::factory()->create([
        'admin_id' => $admin->id,
        'assignee_id' => $staffOne->id,
        'title' => 'Ada filtered task',
    ]);
    $excludedTask = Task::factory()->create([
        'admin_id' => $admin->id,
        'assignee_id' => $staffTwo->id,
        'title' => 'Zoe unfiltered task',
    ]);

    $response = $this->actingAs($admin, 'backpack')->get("/tasks?staff_id={$staffOne->id}");

    $response->assertSuccessful();
    $response->assertSee('Staff Member');
    $response->assertSee('All Staff');
    $response->assertSee($staffOne->name);
    $response->assertSee($staffTwo->name);
    $response->assertSee((string) $staffOne->id);

    $searchResponse = $this->actingAs($admin, 'backpack')->post("/tasks/search?staff_id={$staffOne->id}", [
        'start' => 0,
        'length' => 20,
        'search' => ['value' => ''],
        'datatable_id' => 'crudTable',
    ], [
        'X-Requested-With' => 'XMLHttpRequest',
        'Accept' => 'application/json',
    ]);

    $searchResponse->assertOk();
    $searchResponse->assertSee($includedTask->title);
    $searchResponse->assertDontSee($excludedTask->title);
});

test('the task list shows a status filter and applies it to task search results', function () {
    $admin = User::factory()->admin()->create();
    $staff = User::factory()->staff()->create();

    $includedTask = Task::factory()->create([
        'admin_id' => $admin->id,
        'assignee_id' => $staff->id,
        'title' => 'Completed filtered task',
        'status' => TaskStatus::Completed->value,
    ]);
    $excludedTask = Task::factory()->create([
        'admin_id' => $admin->id,
        'assignee_id' => $staff->id,
        'title' => 'Pending unfiltered task',
        'status' => TaskStatus::Pending->value,
    ]);

    $response = $this->actingAs($admin, 'backpack')->get('/tasks?status=completed');

    $response->assertSuccessful();
    $response->assertSee('Status');
    $response->assertSee('All Statuses');
    $response->assertSee('Pending');
    $response->assertSee('Completed');
    $response->assertSee('value="completed" selected', false);

    $searchResponse = $this->actingAs($admin, 'backpack')->post('/tasks/search?status=completed', [
        'start' => 0,
        'length' => 20,
        'search' => ['value' => ''],
        'datatable_id' => 'crudTable',
    ], [
        'X-Requested-With' => 'XMLHttpRequest',
        'Accept' => 'application/json',
    ]);

    $searchResponse->assertOk();
    $searchResponse->assertSee($includedTask->title);
    $searchResponse->assertDontSee($excludedTask->title);
});

test('admins see a pending approvals sidebar badge and can open the approval queue', function () {
    $admin = User::factory()->admin()->create();
    $staff = User::factory()->staff()->create();

    $pendingApprovalTask = Task::factory()->create([
        'admin_id' => $admin->id,
        'assignee_id' => $staff->id,
        'title' => 'Needs approval',
        'status' => TaskStatus::Completed->value,
        'approved_as_completed' => false,
    ]);
    Task::factory()->create([
        'admin_id' => $admin->id,
        'assignee_id' => $staff->id,
        'title' => 'Already approved',
        'status' => TaskStatus::Completed->value,
        'approved_as_completed' => true,
    ]);

    $dashboardResponse = $this->actingAs($admin, 'backpack')->get('/dashboard');

    $dashboardResponse->assertSuccessful();
    $dashboardResponse->assertSee('Pending Approvals');
    $dashboardResponse->assertSee('>1<', false);
    $dashboardResponse->assertSee('approval_status=pending');

    $queueResponse = $this->actingAs($admin, 'backpack')->get('/tasks?status=completed&approval_status=pending');

    $queueResponse->assertSuccessful();
    $queueResponse->assertSee('Completion Approval');
    $queueResponse->assertSee('Pending Approval');

    $searchResponse = $this->actingAs($admin, 'backpack')->post('/tasks/search?status=completed&approval_status=pending', [
        'start' => 0,
        'length' => 20,
        'search' => ['value' => ''],
        'datatable_id' => 'crudTable',
    ], [
        'X-Requested-With' => 'XMLHttpRequest',
        'Accept' => 'application/json',
    ]);

    $searchResponse->assertOk();
    $searchResponse->assertSee($pendingApprovalTask->title);
    $searchResponse->assertSee('Approve');
    $searchResponse->assertDontSee('Already approved');
});

test('an admin can approve a completed task from the pending approval queue action', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $staff = User::factory()->staff()->create();
    $task = Task::factory()->create([
        'admin_id' => $admin->id,
        'assignee_id' => $staff->id,
        'status' => TaskStatus::Completed->value,
        'approved_as_completed' => false,
    ]);

    $response = $this->actingAs($admin, 'backpack')->post("/tasks/{$task->id}/approve-completed");

    $response->assertRedirect();

    $task->refresh();

    expect($task->approved_as_completed)->toBeTrue();

    Notification::assertSentTo($staff, TasksApprovedNotification::class, function (TasksApprovedNotification $notification) use ($task): bool {
        return count($notification->tasks) === 1
            && $notification->tasks[0]['title'] === $task->title;
    });
});

test('an admin can approve all pending completed tasks at once', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $staff = User::factory()->staff()->create();

    $firstPendingTask = Task::factory()->create([
        'admin_id' => $admin->id,
        'assignee_id' => $staff->id,
        'status' => TaskStatus::Completed->value,
        'approved_as_completed' => false,
    ]);
    $secondPendingTask = Task::factory()->create([
        'admin_id' => $admin->id,
        'assignee_id' => $staff->id,
        'status' => TaskStatus::Completed->value,
        'approved_as_completed' => false,
    ]);
    $alreadyApprovedTask = Task::factory()->create([
        'admin_id' => $admin->id,
        'assignee_id' => $staff->id,
        'status' => TaskStatus::Completed->value,
        'approved_as_completed' => true,
    ]);

    $queueResponse = $this->actingAs($admin, 'backpack')->get('/tasks?status=completed&approval_status=pending');

    $queueResponse->assertSuccessful();
    $queueResponse->assertSee('Approve All Pending');
    $queueResponse->assertSee("return confirm('Approve all pending completed tasks?');", false);

    $response = $this->actingAs($admin, 'backpack')->post('/tasks/approve-completed-all');

    $response->assertRedirect();

    $firstPendingTask->refresh();
    $secondPendingTask->refresh();
    $alreadyApprovedTask->refresh();

    expect($firstPendingTask->approved_as_completed)->toBeTrue();
    expect($secondPendingTask->approved_as_completed)->toBeTrue();
    expect($alreadyApprovedTask->approved_as_completed)->toBeTrue();

    Notification::assertSentTo($staff, TasksApprovedNotification::class, function (TasksApprovedNotification $notification) use ($firstPendingTask, $secondPendingTask): bool {
        return count($notification->tasks) === 2
            && collect($notification->tasks)->pluck('title')->all() === [
                $firstPendingTask->title,
                $secondPendingTask->title,
            ];
    });
});

test('staff users do not see the staff filter on the task list', function () {
    $staff = User::factory()->staff()->create();

    $response = $this->actingAs($staff, 'backpack')->get('/tasks');

    $response->assertSuccessful();
    $response->assertDontSee('Staff Member');
    $response->assertDontSee('All Staff');
    $response->assertDontSee('staff_id');
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

test('a staff members dashboard shows their open tasks and completion stats for the day', function () {
    $admin = User::factory()->admin()->create();
    $staff = User::factory()->staff()->create();
    $otherStaff = User::factory()->staff()->create();

    $pendingTask = Task::factory()->create([
        'admin_id' => $admin->id,
        'assignee_id' => $staff->id,
        'title' => 'Pending task for staff',
        'scheduled_for' => today()->toDateString(),
        'status' => TaskStatus::Pending->value,
    ]);

    Task::factory()->create([
        'admin_id' => $admin->id,
        'assignee_id' => $staff->id,
        'title' => 'In progress task for staff',
        'scheduled_for' => today()->toDateString(),
        'status' => TaskStatus::InProgress->value,
    ]);

    Task::factory()->create([
        'admin_id' => $admin->id,
        'assignee_id' => $staff->id,
        'title' => 'Completed task for staff',
        'scheduled_for' => today()->toDateString(),
        'status' => TaskStatus::Completed->value,
        'approved_as_completed' => true,
    ]);

    Task::factory()->create([
        'admin_id' => $admin->id,
        'assignee_id' => $staff->id,
        'title' => 'Blocked task for staff',
        'scheduled_for' => today()->toDateString(),
        'status' => TaskStatus::CouldNotBeAchieved->value,
    ]);

    Task::factory()->create([
        'admin_id' => $admin->id,
        'assignee_id' => $otherStaff->id,
        'title' => 'Another staff pending task',
        'scheduled_for' => today()->toDateString(),
        'status' => TaskStatus::Pending->value,
    ]);

    Task::factory()->create([
        'admin_id' => $admin->id,
        'assignee_id' => $staff->id,
        'title' => 'Tomorrow pending task for staff',
        'scheduled_for' => today()->addDay()->toDateString(),
        'status' => TaskStatus::Pending->value,
    ]);

    $response = $this->actingAs($staff, 'backpack')->get('/dashboard');

    $response->assertSuccessful();
    $response->assertSee('Total Assigned Today');
    $response->assertSee('4');
    $response->assertSee('Pending / In Progress');
    $response->assertSee('Completed');
    $response->assertSee('Approved Completed');
    $response->assertSee('Could Not Be Completed');
    $response->assertSee('Completion Rate');
    $response->assertSee('2');
    $response->assertSee('1');
    $response->assertSee('25.0%');
    $response->assertSeeText('My Pending & In Progress Tasks');
    $response->assertSee($pendingTask->title);
    $response->assertSee('In progress task for staff');
    $response->assertSee('In Progress');
    $response->assertSee('Tomorrow pending task for staff');
    $response->assertDontSee('Completed task for staff');
    $response->assertDontSee('Blocked task for staff');
    $response->assertDontSee('Another staff pending task');
});

test('admins can view the summary page with staff totals and status distribution', function () {
    $admin = User::factory()->admin()->create();
    $staffOne = User::factory()->staff()->create([
        'name' => 'Ada Staff',
        'email' => 'ada@example.com',
    ]);
    $staffTwo = User::factory()->staff()->create([
        'name' => 'Zoe Staff',
        'email' => 'zoe@example.com',
    ]);

    Task::factory()->create([
        'admin_id' => $admin->id,
        'assignee_id' => $staffOne->id,
        'title' => 'Ada pending task',
        'scheduled_for' => '2026-08-05',
        'status' => TaskStatus::Pending->value,
    ]);
    Task::factory()->create([
        'admin_id' => $admin->id,
        'assignee_id' => $staffOne->id,
        'title' => 'Ada completed task',
        'scheduled_for' => '2026-08-06',
        'status' => TaskStatus::Completed->value,
        'approved_as_completed' => true,
    ]);
    Task::factory()->create([
        'admin_id' => $admin->id,
        'assignee_id' => $staffOne->id,
        'title' => 'Ada awaiting approval task',
        'scheduled_for' => '2026-08-06',
        'status' => TaskStatus::Completed->value,
        'approved_as_completed' => false,
    ]);
    Task::factory()->create([
        'admin_id' => $admin->id,
        'assignee_id' => $staffTwo->id,
        'title' => 'Zoe blocked task',
        'scheduled_for' => '2026-08-06',
        'status' => TaskStatus::CouldNotBeAchieved->value,
    ]);
    Task::factory()->create([
        'admin_id' => $admin->id,
        'assignee_id' => $staffTwo->id,
        'title' => 'Zoe outside range task',
        'scheduled_for' => '2026-09-01',
        'status' => TaskStatus::Completed->value,
        'approved_as_completed' => true,
    ]);

    $response = $this->actingAs($admin, 'backpack')->get('/summary?start_date=2026-08-01&end_date=2026-08-31');

    $response->assertSuccessful();
    $response->assertSee('Task Summary');
    $response->assertSee('Task Status Distribution (Aug 1, 2026 - Aug 31, 2026)');
    $response->assertSee('Staff Summary');
    $response->assertSee('mobile-table-scroll');
    $response->assertSee('min-width: 760px;', false);
    $response->assertSee('Ada Staff');
    $response->assertSee('ada@example.com');
    $response->assertSee('Zoe Staff');
    $response->assertSee('zoe@example.com');
    $response->assertSee('All'); // ensure page renders table/legend text from statuses area
    $response->assertSee('Pending');
    $response->assertSee('Completed');
    $response->assertSee('Could Not Be Achieved');
    $response->assertSee('33.3%');
    $response->assertDontSee('Zoe outside range task');
});

test('admins can approve a completed task from the edit form', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $staff = User::factory()->staff()->create();
    $task = Task::factory()->create([
        'admin_id' => $admin->id,
        'assignee_id' => $staff->id,
        'status' => TaskStatus::Completed->value,
        'approved_as_completed' => false,
    ]);

    $response = $this->actingAs($admin, 'backpack')->put("/tasks/{$task->id}", [
        'id' => $task->id,
        'title' => $task->title,
        'description' => $task->description,
        'scheduled_for' => $task->scheduled_for->toDateString(),
        'status' => TaskStatus::Completed->value,
        'approved_as_completed' => '1',
        'sort_order' => $task->sort_order,
        'outcome_notes' => $task->outcome_notes,
        'assignee_id' => $staff->id,
    ]);

    $response->assertRedirect();

    $task->refresh();

    expect($task->approved_as_completed)->toBeTrue();

    Notification::assertSentTo($staff, TasksApprovedNotification::class, function (TasksApprovedNotification $notification) use ($task): bool {
        return count($notification->tasks) === 1
            && $notification->tasks[0]['title'] === $task->title;
    });
});

test('the summary page shows all time when no date range is selected', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin, 'backpack')->get('/summary');

    $response->assertSuccessful();
    $response->assertSee('Task Status Distribution (All Time)');
});

test('staff users cannot access the summary page', function () {
    $staff = User::factory()->staff()->create();

    $response = $this->actingAs($staff, 'backpack')->get('/summary');

    $response->assertForbidden();
});
