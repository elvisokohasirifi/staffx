<?php

namespace App\Actions\Tasks;

use App\Models\Task;
use App\Models\User;
use App\Notifications\TasksApprovedNotification;
use App\Notifications\TasksAssignedNotification;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class SendTaskNotificationsAction
{
    /**
     * @param  Collection<int, Task>  $tasks
     */
    public function sendAssigned(Collection $tasks): void
    {
        $this->notify($tasks, static fn (User $staff, Collection $groupedTasks): TasksAssignedNotification => new TasksAssignedNotification(
            staffName: $staff->name,
            tasks: self::buildPayload($groupedTasks),
        ));
    }

    /**
     * @param  Collection<int, Task>  $tasks
     */
    public function sendApproved(Collection $tasks): void
    {
        $this->notify($tasks, static fn (User $staff, Collection $groupedTasks): TasksApprovedNotification => new TasksApprovedNotification(
            staffName: $staff->name,
            tasks: self::buildPayload($groupedTasks),
        ));
    }

    /**
     * @param  Collection<int, Task>  $tasks
     * @param  callable(User, Collection<int, Task>): object  $notificationFactory
     */
    private function notify(Collection $tasks, callable $notificationFactory): void
    {
        if ($tasks->isEmpty()) {
            return;
        }

        (new EloquentCollection($tasks->all()))
            ->loadMissing('assignee')
            ->filter(fn (Task $task): bool => $task->assignee instanceof User)
            ->groupBy('assignee_id')
            ->each(function (Collection $groupedTasks) use ($notificationFactory): void {
                /** @var User $staff */
                $staff = $groupedTasks->first()->assignee;
                $staff->notify($notificationFactory($staff, $groupedTasks));
            });
    }

    /**
     * @param  Collection<int, Task>  $tasks
     * @return array<int, array{title: string, scheduled_for: string}>
     */
    private static function buildPayload(Collection $tasks): array
    {
        return $tasks
            ->map(fn (Task $task): array => [
                'title' => $task->title,
                'scheduled_for' => $task->scheduledAtLabel(),
            ])
            ->all();
    }
}
