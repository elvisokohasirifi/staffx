<?php

namespace App\Models;

use App\Models\Traits\LogsActivity;
use App\TaskStatus;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'title',
    'description',
    'scheduled_for',
    'status',
    'approved_as_completed',
    'sort_order',
    'outcome_notes',
    'admin_id',
    'assignee_id',
])]
class Task extends Model
{
    use CrudTrait;

    /** @use HasFactory<TaskFactory> */
    use HasFactory;

    use HasUuids;
    use LogsActivity;

    public string $identifiableAttribute = 'title';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scheduled_for' => 'date',
            'status' => TaskStatus::class,
            'approved_as_completed' => 'boolean',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Task $task): void {
            if (is_null($task->sort_order)) {
                $task->sort_order = 1;
            }

            if ($task->status !== TaskStatus::Completed) {
                $task->approved_as_completed = false;
            }

            if ($task->status === TaskStatus::InProgress && is_null($task->started_at)) {
                $task->started_at = now();
            }

            if (in_array($task->status, [TaskStatus::Completed, TaskStatus::CouldNotBeAchieved], true) && is_null($task->completed_at)) {
                $task->completed_at = now();
            }

            if ($task->status === TaskStatus::Pending) {
                $task->started_at = null;
                $task->completed_at = null;
            }

            if ($task->status === TaskStatus::InProgress) {
                $task->completed_at = null;
            }
        });
    }

    public function scopeSummaryPending(Builder $query): Builder
    {
        return $query->where(function (Builder $builder): void {
            $builder
                ->where('status', TaskStatus::Pending->value)
                ->orWhere(function (Builder $nestedBuilder): void {
                    $nestedBuilder
                        ->where('status', TaskStatus::Completed->value)
                        ->where('approved_as_completed', false);
                });
        });
    }

    public function scopeSummaryCompleted(Builder $query): Builder
    {
        return $query
            ->where('status', TaskStatus::Completed->value)
            ->where('approved_as_completed', true);
    }

    public function summaryStatus(): TaskStatus
    {
        if ($this->status === TaskStatus::Completed && ! $this->approved_as_completed) {
            return TaskStatus::Pending;
        }

        return $this->status;
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function remarks(): HasMany
    {
        return $this->hasMany(TaskRemark::class);
    }
}
