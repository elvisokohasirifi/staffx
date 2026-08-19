<?php

use App\Actions\Tasks\GenerateRecurringTasksAction;
use App\Actions\Tasks\SendTaskNotificationsAction;
use App\Jobs\GenerateRecurringTasksJob;
use App\Models\RecurringTask;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TasksAssignedNotification;
use App\TaskStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

test('an admin can create a recurring task and it immediately assigns todays task when the pattern applies', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $staff = User::factory()->staff()->create();

    $response = $this->actingAs($admin, 'backpack')->post('/recurring-tasks', [
        'title' => 'Morning devotion follow-up',
        'description' => 'Check and confirm daily devotion feedback.',
        'assignee_id' => $staff->id,
        'scheduled_time' => '08:30',
        'recurring_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
        'is_active' => '1',
    ]);

    $response->assertRedirect();

    $recurringTask = RecurringTask::query()->first();

    expect($recurringTask)->not->toBeNull();
    expect($recurringTask?->admin_id)->toBe($admin->id);
    expect($recurringTask?->assignee_id)->toBe($staff->id);
    expect($recurringTask?->recurring_days)->toBe(['monday', 'tuesday', 'wednesday', 'thursday', 'friday']);

    $generatedTask = Task::query()->where('recurring_task_id', $recurringTask?->id)->first();

    expect($generatedTask)->not->toBeNull();
    expect($generatedTask?->title)->toBe('Morning devotion follow-up');
    expect($generatedTask?->scheduled_for?->toDateString())->toBe(today()->toDateString());
    expect($generatedTask?->status)->toBe(TaskStatus::Pending);

    Notification::assertSentTo($staff, TasksAssignedNotification::class, function (TasksAssignedNotification $notification): bool {
        return count($notification->tasks) === 1
            && $notification->tasks[0]['title'] === 'Morning devotion follow-up';
    });
});

test('the recurring task generator creates tasks only for the selected recurring days', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $staff = User::factory()->staff()->create();

    RecurringTask::factory()->create([
        'admin_id' => $admin->id,
        'assignee_id' => $staff->id,
        'title' => 'Weekday template',
        'recurring_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
    ]);
    RecurringTask::factory()->create([
        'admin_id' => $admin->id,
        'assignee_id' => $staff->id,
        'title' => 'Saturday template',
        'recurring_days' => ['saturday'],
    ]);
    RecurringTask::factory()->create([
        'admin_id' => $admin->id,
        'assignee_id' => $staff->id,
        'title' => 'Sunday template',
        'recurring_days' => ['sunday'],
    ]);
    RecurringTask::factory()->create([
        'admin_id' => $admin->id,
        'assignee_id' => $staff->id,
        'title' => 'Everyday template',
        'recurring_days' => array_keys(RecurringTask::recurringDayOptions()),
    ]);

    $this->travelTo(now()->startOfWeek()->addDays(5));

    app(GenerateRecurringTasksJob::class)->handle(
        app(GenerateRecurringTasksAction::class),
        app(SendTaskNotificationsAction::class),
    );

    expect(Task::query()->pluck('title')->all())->toEqualCanonicalizing([
        'Saturday template',
        'Everyday template',
    ]);

    $this->travelBack();
});

test('the recurring task generator skips duplicates when it runs more than once for the same day', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $staff = User::factory()->staff()->create();

    RecurringTask::factory()->create([
        'admin_id' => $admin->id,
        'assignee_id' => $staff->id,
        'title' => 'First recurring task',
        'recurring_days' => array_keys(RecurringTask::recurringDayOptions()),
    ]);
    RecurringTask::factory()->create([
        'admin_id' => $admin->id,
        'assignee_id' => $staff->id,
        'title' => 'Second recurring task',
        'recurring_days' => array_keys(RecurringTask::recurringDayOptions()),
    ]);

    $job = app(GenerateRecurringTasksJob::class);

    $job->handle(
        app(GenerateRecurringTasksAction::class),
        app(SendTaskNotificationsAction::class),
    );
    $job->handle(
        app(GenerateRecurringTasksAction::class),
        app(SendTaskNotificationsAction::class),
    );

    expect(Task::query()->count())->toBe(2);

    Notification::assertSentTo($staff, TasksAssignedNotification::class, function (TasksAssignedNotification $notification): bool {
        return count($notification->tasks) === 2;
    });
    expect(Notification::sent($staff, TasksAssignedNotification::class))->toHaveCount(1);
});

test('admins can view recurring tasks and staff cannot', function () {
    $admin = User::factory()->admin()->create();
    $staff = User::factory()->staff()->create();

    $adminResponse = $this->actingAs($admin, 'backpack')->get('/recurring-tasks');

    $adminResponse->assertSuccessful();
    $adminResponse->assertSee('Recurring Tasks');
    $adminResponse->assertSee('Recurring Days');

    $staffResponse = $this->actingAs($staff, 'backpack')->get('/recurring-tasks');

    $staffResponse->assertForbidden();
});

test('the recurring task list shows filters and applies them to search results', function () {
    $admin = User::factory()->admin()->create();
    $staffOne = User::factory()->staff()->create(['name' => 'Ada Staff']);
    $staffTwo = User::factory()->staff()->create(['name' => 'Zoe Staff']);

    $includedRecurringTask = RecurringTask::factory()->create([
        'admin_id' => $admin->id,
        'assignee_id' => $staffOne->id,
        'title' => 'Ada daily briefing',
        'recurring_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
        'is_active' => true,
    ]);
    RecurringTask::factory()->create([
        'admin_id' => $admin->id,
        'assignee_id' => $staffTwo->id,
        'title' => 'Zoe weekend check-in',
        'recurring_days' => ['sunday'],
        'is_active' => false,
    ]);

    $response = $this->actingAs($admin, 'backpack')->get("/recurring-tasks?staff_id={$staffOne->id}&recurring_day=monday&active=1");

    $response->assertSuccessful();
    $response->assertSee('Filters');
    $response->assertSee('Staff Member');
    $response->assertSee('Recurring Day');
    $response->assertSee('Active Status');

    $searchResponse = $this->actingAs($admin, 'backpack')->post("/recurring-tasks/search?staff_id={$staffOne->id}&recurring_day=monday&active=1", [
        'start' => 0,
        'length' => 20,
        'search' => ['value' => ''],
        'datatable_id' => 'crudTable',
    ], [
        'X-Requested-With' => 'XMLHttpRequest',
        'Accept' => 'application/json',
    ]);

    $searchResponse->assertOk();
    $searchResponse->assertSee($includedRecurringTask->title);
    $searchResponse->assertDontSee('Zoe weekend check-in');
});

test('an admin can bulk create recurring tasks for one staff member', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $staff = User::factory()->staff()->create([
        'name' => 'Recurring Staff',
        'email' => 'recurring-staff@example.com',
    ]);

    $response = $this->actingAs($admin, 'backpack')->post('/recurring-tasks/bulk-create', [
        'assignee_id' => $staff->id,
        'scheduled_time' => '07:45',
        'recurring_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
        'is_active' => '1',
        'task_lines' => "Morning attendance\nSend daily reminder\nPrepare follow-up sheet",
    ]);

    $response->assertRedirect('/recurring-tasks');

    $recurringTasks = RecurringTask::query()
        ->where('admin_id', $admin->id)
        ->where('assignee_id', $staff->id)
        ->orderBy('title')
        ->get();

    expect($recurringTasks)->toHaveCount(3);
    expect($recurringTasks->pluck('title')->all())->toBe([
        'Morning attendance',
        'Prepare follow-up sheet',
        'Send daily reminder',
    ]);
    expect($recurringTasks->every(fn (RecurringTask $recurringTask): bool => $recurringTask->scheduled_time === '07:45:00'))->toBeTrue();
    expect($recurringTasks->every(fn (RecurringTask $recurringTask): bool => $recurringTask->recurring_days === ['monday', 'tuesday', 'wednesday', 'thursday', 'friday']))->toBeTrue();

    $generatedTasks = Task::query()
        ->whereIn('recurring_task_id', $recurringTasks->pluck('id'))
        ->orderBy('title')
        ->get();

    expect($generatedTasks)->toHaveCount(3);
    expect($generatedTasks->pluck('title')->all())->toBe([
        'Morning attendance',
        'Prepare follow-up sheet',
        'Send daily reminder',
    ]);

    Notification::assertSentTo($staff, TasksAssignedNotification::class, function (TasksAssignedNotification $notification): bool {
        return count($notification->tasks) === 3;
    });
});
