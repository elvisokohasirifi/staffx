<?php

namespace App\Models;

use App\Models\Traits\LogsActivity;
use App\RecurringTaskPattern;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Database\Factories\RecurringTaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

#[Fillable([
    'title',
    'description',
    'scheduled_time',
    'repeat_pattern',
    'is_active',
    'admin_id',
    'assignee_id',
])]
class RecurringTask extends Model
{
    use CrudTrait;

    /** @use HasFactory<RecurringTaskFactory> */
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
            'repeat_pattern' => RecurringTaskPattern::class,
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (RecurringTask $recurringTask): void {
            if (blank($recurringTask->scheduled_time)) {
                $recurringTask->scheduled_time = '23:59:00';
            } else {
                $recurringTask->scheduled_time = self::normalizeScheduledTime((string) $recurringTask->scheduled_time);
            }
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scheduledTimeLabel(): string
    {
        try {
            return Carbon::createFromFormat('H:i:s', (string) $this->scheduled_time)->format('g:i A');
        } catch (\Throwable) {
            return (string) $this->scheduled_time;
        }
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function generatedTasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    private static function normalizeScheduledTime(string $value): string
    {
        try {
            return Carbon::createFromFormat('H:i:s', $value)->format('H:i:s');
        } catch (\Throwable) {
            return Carbon::createFromFormat('H:i', $value)->format('H:i:s');
        }
    }
}
