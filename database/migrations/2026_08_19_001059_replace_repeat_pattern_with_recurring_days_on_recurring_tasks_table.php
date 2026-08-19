<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('recurring_tasks')) {
            return;
        }

        if (! Schema::hasColumn('recurring_tasks', 'recurring_days')) {
            Schema::table('recurring_tasks', function (Blueprint $table) {
                $table->json('recurring_days')->nullable()->after('scheduled_time');
            });
        }

        if (Schema::hasColumn('recurring_tasks', 'repeat_pattern')) {
            DB::table('recurring_tasks')
                ->select(['id', 'repeat_pattern'])
                ->orderBy('id')
                ->get()
                ->each(function (object $recurringTask): void {
                    DB::table('recurring_tasks')
                        ->where('id', $recurringTask->id)
                        ->update([
                            'recurring_days' => json_encode($this->daysForPattern((string) $recurringTask->repeat_pattern), JSON_THROW_ON_ERROR),
                        ]);
                });

            Schema::table('recurring_tasks', function (Blueprint $table) {
                $table->dropColumn('repeat_pattern');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('recurring_tasks')) {
            return;
        }

        if (! Schema::hasColumn('recurring_tasks', 'repeat_pattern')) {
            Schema::table('recurring_tasks', function (Blueprint $table) {
                $table->enum('repeat_pattern', [
                    'weekdays',
                    'weekdays_and_saturday',
                    'weekdays_and_sunday',
                    'everyday',
                ])->default('weekdays')->nullable();
            });
        }

        if (Schema::hasColumn('recurring_tasks', 'recurring_days')) {
            DB::table('recurring_tasks')
                ->select(['id', 'recurring_days'])
                ->orderBy('id')
                ->get()
                ->each(function (object $recurringTask): void {
                    $decodedDays = json_decode((string) $recurringTask->recurring_days, true);

                    DB::table('recurring_tasks')
                        ->where('id', $recurringTask->id)
                        ->update([
                            'repeat_pattern' => $this->patternForDays(is_array($decodedDays) ? $decodedDays : []),
                        ]);
                });

            Schema::table('recurring_tasks', function (Blueprint $table) {
                $table->dropColumn('recurring_days');
            });
        }
    }

    /**
     * @return array<int, string>
     */
    private function daysForPattern(string $pattern): array
    {
        return match ($pattern) {
            'weekdays_and_saturday' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'],
            'weekdays_and_sunday' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'sunday'],
            'everyday' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
            default => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
        };
    }

    /**
     * @param  array<int, string>  $days
     */
    private function patternForDays(array $days): string
    {
        sort($days);

        return match ($days) {
            ['friday', 'monday', 'saturday', 'sunday', 'thursday', 'tuesday', 'wednesday'] => 'everyday',
            ['friday', 'monday', 'saturday', 'thursday', 'tuesday', 'wednesday'] => 'weekdays_and_saturday',
            ['friday', 'monday', 'sunday', 'thursday', 'tuesday', 'wednesday'] => 'weekdays_and_sunday',
            default => 'weekdays',
        };
    }
};
