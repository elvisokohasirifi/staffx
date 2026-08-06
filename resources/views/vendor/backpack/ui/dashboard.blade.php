@extends(backpack_view('blank'))

@section('content')
    @php
        $stats = $taskDashboardStats ?? [
            'due_today' => 0,
            'pending' => 0,
            'in_progress' => 0,
            'completed' => 0,
            'could_not_be_achieved' => 0,
        ];
        $todayTasks = $adminTodayTasks ?? collect();
        $pendingTasks = $staffPendingTasks ?? collect();
    @endphp

    <div class="mb-4">
        <h2 class="mb-1">{{ backpack_user()?->isAdmin() ? 'Admin Dashboard' : 'My Dashboard' }}</h2>
        <p class="text-muted mb-0">
            {{ backpack_user()?->isAdmin() ? 'Task summary for '.today()->toFormattedDateString().'.' : 'Pending tasks due today.' }}
        </p>
    </div>

    @if(backpack_user()?->isAdmin())
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-xl-3">
                <div class="card border-0 bg-primary text-white h-100">
                    <div class="card-body">
                        <div class="text-uppercase small fw-semibold">Due Today</div>
                        <div class="display-6 fw-bold">{{ $stats['due_today'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card border-0 bg-warning text-dark h-100">
                    <div class="card-body">
                        <div class="text-uppercase small fw-semibold">Pending</div>
                        <div class="display-6 fw-bold">{{ $stats['pending'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card border-0 bg-info text-white h-100">
                    <div class="card-body">
                        <div class="text-uppercase small fw-semibold">In Progress</div>
                        <div class="display-6 fw-bold">{{ $stats['in_progress'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card border-0 bg-success text-white h-100">
                    <div class="card-body">
                        <div class="text-uppercase small fw-semibold">Completed</div>
                        <div class="display-6 fw-bold">{{ $stats['completed'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card border-0 bg-danger text-white h-100">
                    <div class="card-body">
                        <div class="text-uppercase small fw-semibold">Could Not Be Achieved</div>
                        <div class="display-6 fw-bold">{{ $stats['could_not_be_achieved'] }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h4 class="mb-0">Tasks Due Today</h4>
            </div>
            <div class="card-body p-0">
                @if($todayTasks->isEmpty())
                    <div class="p-4 text-muted">There are no tasks due today.</div>
                @else
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th>Staff</th>
                                    <th>Task</th>
                                    <th>Status</th>
                                    <th>Remarks</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($todayTasks as $task)
                                    <tr>
                                        <td>{{ $task->assignee?->name ?? 'Unassigned' }}</td>
                                        <td>
                                            <div class="fw-semibold">{{ $task->title }}</div>
                                            @if($task->description)
                                                <div class="text-muted small">{{ \Illuminate\Support\Str::limit($task->description, 100) }}</div>
                                            @endif
                                        </td>
                                        <td>{{ \App\TaskStatus::options()[$task->summaryStatus()->value] ?? $task->summaryStatus()->value }}</td>
                                        <td>{{ $task->remarks_count }}</td>
                                        <td class="text-end">
                                            <a href="{{ backpack_url("tasks/{$task->getKey()}/show") }}" class="btn btn-sm btn-outline-primary">Open Task</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    @else
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card border-0 bg-warning text-dark h-100">
                    <div class="card-body">
                        <div class="text-uppercase small fw-semibold">Pending Tasks</div>
                        <div class="display-6 fw-bold">{{ $pendingTasks->count() }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h4 class="mb-0">My Pending Tasks Due Today</h4>
            </div>
            <div class="card-body p-0">
                @if($pendingTasks->isEmpty())
                    <div class="p-4 text-muted">You do not have any pending tasks due today.</div>
                @else
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th>Scheduled</th>
                                    <th>Task</th>
                                    <th>Remarks</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($pendingTasks as $task)
                                    <tr>
                                        <td>{{ $task->scheduled_for?->format('M j, Y') }}</td>
                                        <td>
                                            <div class="fw-semibold">{{ $task->title }}</div>
                                            @if($task->description)
                                                <div class="text-muted small">{{ \Illuminate\Support\Str::limit($task->description, 100) }}</div>
                                            @endif
                                        </td>
                                        <td>{{ $task->remarks_count }}</td>
                                        <td class="text-end">
                                            <a href="{{ backpack_url("tasks/{$task->getKey()}/show") }}" class="btn btn-sm btn-outline-primary">Open Task</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    @endif
@endsection
