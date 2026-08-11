<?php

namespace App\Jobs;

use App\Models\Task;
use App\Models\User;
use App\Notifications\PendingTasksReminderNotification;
use App\TaskStatus;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;

class SendPendingTaskRemindersJob implements ShouldQueue
{
    use Queueable;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Task::query()
            ->with('assignee')
            ->whereDate('scheduled_for', today())
            ->where(function ($query): void {
                $query
                    ->where('status', '!=', TaskStatus::Completed->value)
                    ->orWhere('approved_as_completed', false);
            })
            ->get()
            ->filter(fn (Task $task): bool => $task->assignee instanceof User)
            ->groupBy('assignee_id')
            ->each(function (Collection $groupedTasks): void {
                /** @var User $staff */
                $staff = $groupedTasks->first()->assignee;

                $staff->notify(new PendingTasksReminderNotification(
                    staffName: $staff->name,
                    tasks: $groupedTasks->map(fn (Task $task): array => [
                        'title' => $task->title,
                        'status' => TaskStatus::options()[$task->summaryStatus()->value] ?? $task->summaryStatus()->value,
                    ])->all(),
                ));
            });
    }
}
