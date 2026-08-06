<?php

namespace App\Providers;

use App\Models\Task;
use App\TaskStatus;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer(array_unique([
            backpack_view('dashboard'),
            'backpack.ui::dashboard',
            'backpack.theme-tabler::dashboard',
        ]), function ($view): void {
            $statsQuery = Task::query()->whereDate('scheduled_for', today());
            $adminTodayTasks = collect();
            $pendingTasks = collect();

            if (backpack_auth()->check()) {
                if (backpack_user()?->isAdmin()) {
                    $adminTodayTasks = Task::query()
                        ->whereDate('scheduled_for', today())
                        ->leftJoin('users as assignees', 'assignees.id', '=', 'tasks.assignee_id')
                        ->select('tasks.*')
                        ->with(['assignee'])
                        ->withCount('remarks')
                        ->orderBy('assignees.name')
                        ->orderBy('tasks.title')
                        ->get();
                } else {
                    $statsQuery->where('assignee_id', backpack_user()->getKey());

                    $pendingTasks = Task::query()
                        ->where('assignee_id', backpack_user()->getKey())
                        ->whereDate('scheduled_for', today())
                        ->where('status', TaskStatus::Pending->value)
                        ->withCount('remarks')
                        ->orderBy('scheduled_for')
                        ->orderBy('sort_order')
                        ->get();
                }
            }

            $todayTasks = (clone $statsQuery)->count();
            $completedTasks = (clone $statsQuery)->summaryCompleted()->count();

            $view->with('taskDashboardStats', [
                'due_today' => $todayTasks,
                'pending' => (clone $statsQuery)->summaryPending()->count(),
                'in_progress' => (clone $statsQuery)->where('status', TaskStatus::InProgress->value)->count(),
                'completed' => $completedTasks,
                'could_not_be_achieved' => (clone $statsQuery)->where('status', TaskStatus::CouldNotBeAchieved->value)->count(),
                'completion_rate' => $todayTasks > 0 ? round(($completedTasks / $todayTasks) * 100, 1) : 0.0,
            ]);
            $view->with('adminTodayTasks', $adminTodayTasks);
            $view->with('staffPendingTasks', $pendingTasks);
        });
    }
}
