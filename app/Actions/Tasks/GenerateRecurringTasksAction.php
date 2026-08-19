<?php

namespace App\Actions\Tasks;

use App\Models\RecurringTask;
use App\Models\Task;
use App\TaskStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GenerateRecurringTasksAction
{
    /**
     * @param  Collection<int, RecurringTask>|EloquentCollection<int, RecurringTask>|null  $templates
     * @return Collection<int, Task>
     */
    public function execute(CarbonInterface $date, Collection|EloquentCollection|null $templates = null): Collection
    {
        $targetDate = $date->copy()->startOfDay();
        $recurringTasks = $templates instanceof EloquentCollection
            ? $templates
            : new EloquentCollection(($templates ?? $this->baseQuery()->get())->all());

        $recurringTasks->loadMissing(['admin', 'assignee']);

        $createdTasks = collect();
        $sortOrders = [];

        DB::transaction(function () use ($createdTasks, $recurringTasks, &$sortOrders, $targetDate): void {
            foreach ($recurringTasks as $recurringTask) {
                if (! $recurringTask->is_active || ! $recurringTask->recursOnDate($targetDate)) {
                    continue;
                }

                $existingTask = Task::query()
                    ->where('recurring_task_id', $recurringTask->getKey())
                    ->whereDate('scheduled_for', $targetDate->toDateString())
                    ->exists();

                if ($existingTask) {
                    continue;
                }

                $sortOrderKey = implode('|', [$recurringTask->assignee_id, $targetDate->toDateString()]);

                if (! array_key_exists($sortOrderKey, $sortOrders)) {
                    $sortOrders[$sortOrderKey] = (int) Task::query()
                        ->staffTasks()
                        ->where('assignee_id', $recurringTask->assignee_id)
                        ->whereDate('scheduled_for', $targetDate->toDateString())
                        ->max('sort_order');
                }

                $sortOrders[$sortOrderKey]++;

                $createdTasks->push(Task::query()->create([
                    'title' => $recurringTask->title,
                    'description' => $recurringTask->description,
                    'scheduled_for' => $targetDate->toDateString(),
                    'scheduled_time' => $recurringTask->scheduled_time,
                    'status' => TaskStatus::Pending,
                    'approved_as_completed' => false,
                    'sort_order' => $sortOrders[$sortOrderKey],
                    'admin_id' => $recurringTask->admin_id,
                    'assignee_id' => $recurringTask->assignee_id,
                    'recurring_task_id' => $recurringTask->getKey(),
                ]));
            }
        });

        return $createdTasks;
    }

    /**
     * @return Builder<RecurringTask>
     */
    private function baseQuery()
    {
        return RecurringTask::query()
            ->active()
            ->with(['admin', 'assignee'])
            ->orderBy('assignee_id')
            ->orderBy('title');
    }
}
