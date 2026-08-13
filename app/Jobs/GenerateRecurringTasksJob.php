<?php

namespace App\Jobs;

use App\Actions\Tasks\GenerateRecurringTasksAction;
use App\Actions\Tasks\SendTaskNotificationsAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateRecurringTasksJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(
        GenerateRecurringTasksAction $generateRecurringTasks,
        SendTaskNotificationsAction $sendTaskNotifications,
    ): void {
        $createdTasks = $generateRecurringTasks->execute(today());

        $sendTaskNotifications->sendAssigned($createdTasks);
    }
}
