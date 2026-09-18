<?php

namespace App\Services\PerformanceInsights;

use App\Models\Task;
use App\Models\User;
use App\TaskStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PerformanceQuestionService
{
    /**
     * Interpret a supported performance question using task aggregates only.
     *
     * @return array<string, mixed>
     */
    public function answer(string $question): array
    {
        $period = $this->periodFor($question);
        $metric = $this->metricFor($question);
        $staffMembers = $this->staffForQuestion($question);
        $aggregates = $this->aggregateForPeriod($staffMembers, $period['start'], $period['end']);
        $values = $staffMembers->mapWithKeys(function (User $staffMember) use ($aggregates, $metric): array {
            return [$staffMember->getKey() => $this->metricValue($aggregates->get($staffMember->getKey()), $metric)];
        });
        $usesComparison = $this->usesComparison($question, $staffMembers);
        $resolvedChartType = $this->chartTypeFor($question);

        return [
            'answer' => $this->answerText($staffMembers, $values, $metric, $period['label'], $usesComparison),
            'metric' => $metric,
            'metric_label' => $this->metricLabel($metric),
            'period_label' => $period['label'],
            'start_date' => $period['start']->toDateString(),
            'end_date' => $period['end']->toDateString(),
            'chart' => $usesComparison && $resolvedChartType !== 'pie'
                ? $this->monthlyChart($staffMembers, $period['start'], $period['end'], $metric, $resolvedChartType)
                : [
                    'type' => $resolvedChartType,
                    'labels' => $staffMembers->pluck('name')->values()->all(),
                    'datasets' => [[
                        'label' => $this->metricLabel($metric),
                        'values' => $staffMembers->map(fn (User $staffMember): float => $values->get($staffMember->getKey(), 0))->values()->all(),
                    ]],
                ],
        ];
    }

    /**
     * @return array{start: Carbon, end: Carbon, label: string}
     */
    private function periodFor(string $question): array
    {
        $normalizedQuestion = Str::lower($question);
        $monthNames = 'January|February|March|April|May|June|July|August|September|October|November|December';

        if (preg_match('/\\b('.$monthNames.')\\s+(20\\d{2})\\b/i', $question, $matches) === 1) {
            $start = Carbon::createFromFormat('F Y', $matches[1].' '.$matches[2])->startOfMonth();

            return ['start' => $start, 'end' => $start->copy()->endOfMonth(), 'label' => $start->format('F Y')];
        }

        if (preg_match('/\\b(20\\d{2})\\b/', $normalizedQuestion, $matches) === 1) {
            $start = Carbon::create((int) $matches[1], 1, 1)->startOfYear();

            return ['start' => $start, 'end' => $start->copy()->endOfYear(), 'label' => $start->format('Y')];
        }

        $start = now()->startOfMonth();

        return ['start' => $start, 'end' => $start->copy()->endOfMonth(), 'label' => $start->format('F Y')];
    }

    private function metricFor(string $question): string
    {
        $normalizedQuestion = Str::lower($question);

        return match (true) {
            Str::contains($normalizedQuestion, ['completion rate', 'completion percentage']) => 'completion_rate',
            Str::contains($normalizedQuestion, ['could not', 'not achieved', 'blocked', 'failed']) => 'could_not_be_achieved',
            Str::contains($normalizedQuestion, ['in progress', 'started']) => 'in_progress',
            Str::contains($normalizedQuestion, ['pending', 'outstanding']) => 'pending',
            default => 'approved_completed',
        };
    }

    private function chartTypeFor(string $question): string
    {
        $normalizedQuestion = Str::lower($question);

        return match (true) {
            Str::contains($normalizedQuestion, ['pie', 'distribution', 'share', 'proportion']) => 'pie',
            Str::contains($normalizedQuestion, ['line', 'monthly', 'trend', 'over time']) => 'line',
            default => 'bar',
        };
    }

    /**
     * @return Collection<int, User>
     */
    private function staffForQuestion(string $question): Collection
    {
        $staffMembers = User::query()->staff()->orderBy('name')->get(['id', 'name']);
        $normalizedQuestion = Str::lower($question);
        $namedStaff = $staffMembers->filter(fn (User $staffMember): bool => Str::contains($normalizedQuestion, Str::lower($staffMember->name)));

        return $namedStaff->isNotEmpty() ? $namedStaff->values() : $staffMembers;
    }

    /**
     * @param  Collection<int, User>  $staffMembers
     * @return Collection<string, object>
     */
    private function aggregateForPeriod(Collection $staffMembers, Carbon $startDate, Carbon $endDate): Collection
    {
        if ($staffMembers->isEmpty()) {
            return collect();
        }

        return Task::query()
            ->staffTasks()
            ->whereIn('assignee_id', $staffMembers->modelKeys())
            ->whereDate('scheduled_for', '>=', $startDate->toDateString())
            ->whereDate('scheduled_for', '<=', $endDate->toDateString())
            ->select('assignee_id')
            ->selectRaw('COUNT(*) as assigned_count')
            ->selectRaw('SUM(CASE WHEN status = ? AND approved_as_completed = 1 THEN 1 ELSE 0 END) as approved_completed_count', [TaskStatus::Completed->value])
            ->selectRaw('SUM(CASE WHEN status = ? OR (status = ? AND approved_as_completed = 0) THEN 1 ELSE 0 END) as pending_count', [TaskStatus::Pending->value, TaskStatus::Completed->value])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as in_progress_count', [TaskStatus::InProgress->value])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as could_not_be_achieved_count', [TaskStatus::CouldNotBeAchieved->value])
            ->groupBy('assignee_id')
            ->get()
            ->keyBy('assignee_id');
    }

    private function metricValue(?object $aggregate, string $metric): float
    {
        if ($aggregate === null) {
            return 0;
        }

        if ($metric === 'completion_rate') {
            return (int) $aggregate->assigned_count > 0
                ? round(((int) $aggregate->approved_completed_count / (int) $aggregate->assigned_count) * 100, 1)
                : 0;
        }

        return (float) $aggregate->{$metric.'_count'};
    }

    /**
     * @param  Collection<int, User>  $staffMembers
     * @param  Collection<string, float>  $values
     */
    private function answerText(Collection $staffMembers, Collection $values, string $metric, string $periodLabel, bool $usesComparison): string
    {
        if ($staffMembers->isEmpty()) {
            return "There are no staff members available to analyze for {$periodLabel}.";
        }

        if ($usesComparison) {
            return 'The chart compares '.$this->metricLabel($metric).' for '.$staffMembers->pluck('name')->join(', ', ' and ')." across {$periodLabel}.";
        }

        $bestStaffId = $values->sortDesc()->keys()->first();
        $bestStaff = $staffMembers->first(fn (User $staffMember): bool => $staffMember->getKey() === $bestStaffId);
        $value = $values->get($bestStaffId, 0);

        if ($bestStaff === null) {
            return "There is no performance data available for {$periodLabel}.";
        }

        $formattedValue = $metric === 'completion_rate' ? number_format($value, 1).'%' : number_format($value);

        return "{$bestStaff->name} was the best performer for {$periodLabel} with {$formattedValue} {$this->metricLabel($metric)}.";
    }

    /**
     * @param  Collection<int, User>  $staffMembers
     * @return array<string, mixed>
     */
    private function monthlyChart(Collection $staffMembers, Carbon $startDate, Carbon $endDate, string $metric, string $chartType): array
    {
        $months = collect();
        $cursor = $startDate->copy()->startOfMonth();

        while ($cursor->lte($endDate)) {
            $months->push($cursor->copy());
            $cursor->addMonth();
        }

        $monthlyQuery = Task::query()
            ->staffTasks()
            ->whereIn('assignee_id', $staffMembers->modelKeys())
            ->whereDate('scheduled_for', '>=', $startDate->toDateString())
            ->whereDate('scheduled_for', '<=', $endDate->toDateString())
            ->select('assignee_id')
            ->selectRaw('COUNT(*) as assigned_count')
            ->selectRaw('SUM(CASE WHEN status = ? AND approved_as_completed = 1 THEN 1 ELSE 0 END) as approved_completed_count', [TaskStatus::Completed->value])
            ->selectRaw('SUM(CASE WHEN status = ? OR (status = ? AND approved_as_completed = 0) THEN 1 ELSE 0 END) as pending_count', [TaskStatus::Pending->value, TaskStatus::Completed->value])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as in_progress_count', [TaskStatus::InProgress->value])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as could_not_be_achieved_count', [TaskStatus::CouldNotBeAchieved->value]);

        if ($monthlyQuery->getModel()->getConnection()->getDriverName() === 'sqlite') {
            $monthlyQuery
                ->selectRaw("CAST(strftime('%Y', scheduled_for) AS INTEGER) as year_number")
                ->selectRaw("CAST(strftime('%m', scheduled_for) AS INTEGER) as month_number")
                ->groupBy('assignee_id')
                ->groupByRaw("strftime('%Y', scheduled_for), strftime('%m', scheduled_for)");
        } else {
            $monthlyQuery
                ->selectRaw('YEAR(scheduled_for) as year_number')
                ->selectRaw('MONTH(scheduled_for) as month_number')
                ->groupBy('assignee_id')
                ->groupByRaw('YEAR(scheduled_for), MONTH(scheduled_for)');
        }

        $monthlyAggregates = $monthlyQuery
            ->get()
            ->keyBy(fn (object $aggregate): string => implode('-', [$aggregate->assignee_id, $aggregate->year_number, $aggregate->month_number]));

        $datasets = $staffMembers->map(function (User $staffMember) use ($months, $metric, $monthlyAggregates): array {
            $values = $months->map(function (Carbon $month) use ($staffMember, $metric, $monthlyAggregates): float {
                $aggregate = $monthlyAggregates->get(implode('-', [
                    $staffMember->getKey(),
                    $month->year,
                    $month->month,
                ]));

                return $this->metricValue($aggregate, $metric);
            })->all();

            return ['label' => $staffMember->name, 'values' => $values];
        })->values()->all();

        return [
            'type' => $chartType,
            'labels' => $months->map(fn (Carbon $month): string => $month->format('M'))->all(),
            'datasets' => $datasets,
        ];
    }

    /**
     * @param  Collection<int, User>  $staffMembers
     */
    private function usesComparison(string $question, Collection $staffMembers): bool
    {
        return $staffMembers->count() > 1
            && Str::contains(Str::lower($question), ['compar', 'versus', ' vs ', 'against']);
    }

    private function metricLabel(string $metric): string
    {
        return match ($metric) {
            'completion_rate' => 'completion rate',
            'pending' => 'pending tasks',
            'in_progress' => 'in-progress tasks',
            'could_not_be_achieved' => 'tasks that could not be achieved',
            default => 'approved completed tasks',
        };
    }
}
