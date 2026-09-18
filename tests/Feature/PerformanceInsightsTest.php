<?php

use App\Models\PerformanceQuery;
use App\Models\Task;
use App\Models\User;
use App\TaskStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('an admin can ask for the best staff performer and save the result', function () {
    $admin = User::factory()->admin()->create();
    $topPerformer = User::factory()->staff()->create(['name' => 'Amara Mensah']);
    $otherStaffMember = User::factory()->staff()->create(['name' => 'Kofi Owusu']);

    Task::factory()->count(3)->create([
        'admin_id' => $admin->id,
        'assignee_id' => $topPerformer->id,
        'scheduled_for' => '2026-03-12',
        'status' => TaskStatus::Completed,
        'approved_as_completed' => true,
    ]);
    Task::factory()->create([
        'admin_id' => $admin->id,
        'assignee_id' => $otherStaffMember->id,
        'scheduled_for' => '2026-03-12',
        'status' => TaskStatus::Completed,
        'approved_as_completed' => true,
    ]);

    $response = $this->actingAs($admin, 'backpack')->post('/admin/performance-insights', [
        'question' => 'Which staff person was the best performer in March 2026?',
    ]);

    $savedQuery = PerformanceQuery::query()->firstOrFail();

    $response->assertRedirect(route('performance-insights.show', $savedQuery));
    expect($savedQuery->admin_id)->toBe($admin->id)
        ->and($savedQuery->result_data['answer'])->toContain('Amara Mensah')
        ->and($savedQuery->result_data['chart']['labels'])->toContain('Amara Mensah');

    $this->actingAs($admin, 'backpack')->get(route('performance-insights.show', $savedQuery))
        ->assertSuccessful()
        ->assertSee('Amara Mensah')
        ->assertSee('Previous queries');
});

test('an admin can compare named staff performance by month with a line chart', function () {
    $admin = User::factory()->admin()->create();
    $firstStaffMember = User::factory()->staff()->create(['name' => 'Mabel Agyeman']);
    $secondStaffMember = User::factory()->staff()->create(['name' => 'Yaw Boadu']);

    Task::factory()->create([
        'admin_id' => $admin->id,
        'assignee_id' => $firstStaffMember->id,
        'scheduled_for' => '2026-01-20',
        'status' => TaskStatus::Completed,
        'approved_as_completed' => true,
    ]);
    Task::factory()->count(2)->create([
        'admin_id' => $admin->id,
        'assignee_id' => $secondStaffMember->id,
        'scheduled_for' => '2026-02-20',
        'status' => TaskStatus::Completed,
        'approved_as_completed' => true,
    ]);

    $this->actingAs($admin, 'backpack')->post('/admin/performance-insights', [
        'question' => 'Generate a line chart comparing Mabel Agyeman and Yaw Boadu performance for 2026',
    ])->assertRedirect();

    $savedQuery = PerformanceQuery::query()->firstOrFail();

    expect($savedQuery->result_data['chart']['type'])->toBe('line')
        ->and($savedQuery->result_data['chart']['labels'])->toBe(['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'])
        ->and(collect($savedQuery->result_data['chart']['datasets'])->pluck('label')->all())->toBe(['Mabel Agyeman', 'Yaw Boadu']);
});

test('an admin can generate a pie chart for staff performance', function () {
    $admin = User::factory()->admin()->create();
    $firstStaffMember = User::factory()->staff()->create(['name' => 'Esi Danso']);
    $secondStaffMember = User::factory()->staff()->create(['name' => 'Kojo Asante']);

    Task::factory()->count(2)->create([
        'admin_id' => $admin->id,
        'assignee_id' => $firstStaffMember->id,
        'scheduled_for' => '2026-03-15',
        'status' => TaskStatus::Completed,
        'approved_as_completed' => true,
    ]);
    Task::factory()->create([
        'admin_id' => $admin->id,
        'assignee_id' => $secondStaffMember->id,
        'scheduled_for' => '2026-03-15',
        'status' => TaskStatus::Completed,
        'approved_as_completed' => true,
    ]);

    $this->actingAs($admin, 'backpack')->post('/admin/performance-insights', [
        'question' => 'Show the performance distribution for Esi Danso and Kojo Asante in March 2026',
    ])->assertRedirect();

    $savedQuery = PerformanceQuery::query()->firstOrFail();

    expect($savedQuery->result_data['chart']['type'])->toBe('pie')
        ->and($savedQuery->result_data['chart']['labels'])->toBe(['Esi Danso', 'Kojo Asante'])
        ->and(array_map('floatval', $savedQuery->result_data['chart']['datasets'][0]['values']))->toBe([2.0, 1.0]);
});

test('performance query history is private to its admin and can be deleted', function () {
    $admin = User::factory()->admin()->create();
    $otherAdmin = User::factory()->admin()->create();
    $savedQuery = PerformanceQuery::factory()->for($admin, 'admin')->create();

    $this->actingAs($otherAdmin, 'backpack')->get(route('performance-insights.show', $savedQuery))
        ->assertNotFound();

    $this->actingAs($admin, 'backpack')->delete(route('performance-insights.destroy', $savedQuery))
        ->assertRedirect(route('performance-insights.index'));

    $this->assertModelMissing($savedQuery);
});

test('staff members cannot access performance insights', function () {
    $staffMember = User::factory()->staff()->create();

    $this->actingAs($staffMember, 'backpack')->get('/admin/performance-insights')
        ->assertForbidden();
});
