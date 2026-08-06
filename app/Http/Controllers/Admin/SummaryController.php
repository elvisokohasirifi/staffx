<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\User;
use App\TaskStatus;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SummaryController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(backpack_user()?->isAdmin(), 403);

        $startDate = $this->parseDate($request->query('start_date'));
        $endDate = $this->parseDate($request->query('end_date'));

        $taskSummaryQuery = Task::query();

        if ($startDate !== null) {
            $taskSummaryQuery->whereDate('scheduled_for', '>=', $startDate->toDateString());
        }

        if ($endDate !== null) {
            $taskSummaryQuery->whereDate('scheduled_for', '<=', $endDate->toDateString());
        }

        $staff = User::query()->staff()->orderBy('name')->get(['id', 'name', 'email']);

        $aggregates = (clone $taskSummaryQuery)
            ->selectRaw('
                assignee_id,
                COUNT(*) as assigned_count,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed_count,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as could_not_be_achieved_count
            ', [
                TaskStatus::Completed->value,
                TaskStatus::Pending->value,
                TaskStatus::CouldNotBeAchieved->value,
            ])
            ->groupBy('assignee_id')
            ->get()
            ->keyBy('assignee_id');

        $staffSummaries = $staff->map(function (User $staffMember) use ($aggregates): array {
            $aggregate = $aggregates->get($staffMember->getKey());

            $assignedCount = (int) ($aggregate->assigned_count ?? 0);
            $completedCount = (int) ($aggregate->completed_count ?? 0);
            $pendingCount = (int) ($aggregate->pending_count ?? 0);
            $couldNotBeAchievedCount = (int) ($aggregate->could_not_be_achieved_count ?? 0);
            $completionRate = $assignedCount > 0
                ? round(($completedCount / $assignedCount) * 100, 1)
                : 0.0;

            return [
                'staff' => $staffMember,
                'assigned_count' => $assignedCount,
                'completed_count' => $completedCount,
                'pending_count' => $pendingCount,
                'could_not_be_achieved_count' => $couldNotBeAchievedCount,
                'completion_rate' => $completionRate,
            ];
        });

        $statusCounts = $this->buildStatusCounts(clone $taskSummaryQuery);

        return view('admin.summary.index', [
            'staffSummaries' => $staffSummaries,
            'statusCounts' => $statusCounts,
            'startDate' => $startDate?->toDateString(),
            'endDate' => $endDate?->toDateString(),
            'totalTasks' => $statusCounts->sum('count'),
        ]);
    }

    private function buildStatusCounts($query): Collection
    {
        $counts = $query
            ->select('status', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return collect(TaskStatus::options())->map(function (string $label, string $status) use ($counts): array {
            return [
                'status' => $status,
                'label' => $label,
                'count' => (int) ($counts[$status] ?? 0),
            ];
        })->values();
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
