<?php

namespace App\Providers;

use App\Models\Task;
use App\TaskStatus;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
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
        Event::listen(MessageSent::class, function (MessageSent $event): void {
            $message = $event->message;

            Log::channel('mail')->info('Mail sent', [
                'subject' => $message->getSubject(),
                'from' => collect($message->getFrom() ?? [])->map(fn ($address) => $address->toString())->values()->all(),
                'to' => collect($message->getTo() ?? [])->map(fn ($address) => $address->toString())->values()->all(),
                'cc' => collect($message->getCc() ?? [])->map(fn ($address) => $address->toString())->values()->all(),
                'bcc' => collect($message->getBcc() ?? [])->map(fn ($address) => $address->toString())->values()->all(),
                'html' => $message->getHtmlBody(),
                'text' => $message->getTextBody(),
            ]);
        });

        View::composer([
            'vendor.backpack.ui.inc.menu_items',
            'backpack.ui::inc.menu_items',
            'backpack.theme-tabler::inc.menu_items',
        ], function ($view): void {
            $pendingTaskApprovalCount = 0;

            if (backpack_auth()->check() && backpack_user()?->isAdmin()) {
                $pendingTaskApprovalCount = Task::query()
                    ->staffTasks()
                    ->where('status', TaskStatus::Completed->value)
                    ->where('approved_as_completed', false)
                    ->count();
            }

            $view->with('pendingTaskApprovalCount', $pendingTaskApprovalCount);
        });

        View::composer(array_unique([
            backpack_view('dashboard'),
            'backpack.ui::dashboard',
            'backpack.theme-tabler::dashboard',
        ]), function ($view): void {
            $statsQuery = Task::query()->staffTasks()->whereDate('scheduled_for', today());
            $adminTodayTasks = collect();
            $staffOpenTasks = collect();

            if (backpack_auth()->check()) {
                if (backpack_user()?->isAdmin()) {
                    $adminTodayTasks = Task::query()
                        ->staffTasks()
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

                    $staffOpenTasks = Task::query()
                        ->staffTasks()
                        ->where('assignee_id', backpack_user()->getKey())
                        ->whereIn('status', [
                            TaskStatus::Pending->value,
                            TaskStatus::InProgress->value,
                        ])
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
                'open_tasks' => (clone $statsQuery)->whereIn('status', [
                    TaskStatus::Pending->value,
                    TaskStatus::InProgress->value,
                ])->count(),
                'in_progress' => (clone $statsQuery)->where('status', TaskStatus::InProgress->value)->count(),
                'completed_status' => (clone $statsQuery)->where('status', TaskStatus::Completed->value)->count(),
                'completed' => $completedTasks,
                'could_not_be_achieved' => (clone $statsQuery)->where('status', TaskStatus::CouldNotBeAchieved->value)->count(),
                'completion_rate' => $todayTasks > 0 ? round(($completedTasks / $todayTasks) * 100, 1) : 0.0,
            ]);
            $view->with('adminTodayTasks', $adminTodayTasks);
            $view->with('staffOpenTasks', $staffOpenTasks);
        });
    }
}
