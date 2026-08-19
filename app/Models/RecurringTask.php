<?php

namespace App\Models;

use App\Models\Traits\LogsActivity;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Carbon\CarbonInterface;
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
    'recurring_days',
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
    public static function recurringDayOptions(): array
    {
        return [
            'monday' => 'Monday',
            'tuesday' => 'Tuesday',
            'wednesday' => 'Wednesday',
            'thursday' => 'Thursday',
            'friday' => 'Friday',
            'saturday' => 'Saturday',
            'sunday' => 'Sunday',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function defaultRecurringDays(): array
    {
        return ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'recurring_days' => 'array',
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

            $recurringTask->recurring_days = self::normalizeRecurringDays((array) ($recurringTask->recurring_days ?? []));
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

    public function recurringDaysLabel(): string
    {
        $options = self::recurringDayOptions();

        return collect((array) $this->recurring_days)
            ->map(fn (string $day): string => $options[$day] ?? ucfirst($day))
            ->implode(', ');
    }

    public function recursOnDate(CarbonInterface $date): bool
    {
        return in_array(strtolower($date->format('l')), (array) $this->recurring_days, true);
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

    /**
     * @param  array<int|string, mixed>  $days
     * @return array<int, string>
     */
    private static function normalizeRecurringDays(array $days): array
    {
        $orderedDays = array_keys(self::recurringDayOptions());

        return collect($days)
            ->filter(fn (mixed $day): bool => is_string($day) && array_key_exists($day, self::recurringDayOptions()))
            ->unique()
            ->sortBy(fn (string $day): int => array_search($day, $orderedDays, true))
            ->values()
            ->all();
    }
}
