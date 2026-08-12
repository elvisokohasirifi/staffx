@extends(backpack_view('blank'))

@php
    $defaultBreadcrumbs = [
        trans('backpack::crud.admin') => url(config('backpack.base.route_prefix'), 'dashboard'),
        $crud->entity_name_plural => url($crud->route),
        trans('backpack::crud.list') => false,
    ];

    $breadcrumbs = $breadcrumbs ?? $defaultBreadcrumbs;

    $stats = $myTaskDashboardStats ?? [
        'total_tasks' => 0,
        'open_tasks' => 0,
        'completed_status' => 0,
        'completed' => 0,
        'could_not_be_achieved' => 0,
        'completion_rate' => 0.0,
    ];
    $statusCounts = $myTaskStatusCounts ?? collect();
    $totalTasks = $myTaskTotalCount ?? 0;
    $statusColors = [
        'pending' => '#f0ad4e',
        'in_progress' => '#5bc0de',
        'completed' => '#5cb85c',
        'could_not_be_achieved' => '#d9534f',
    ];
    $circumference = 2 * pi() * 42;
    $runningOffset = 0;
@endphp

@push('after_styles')
    <style>
        .my-task-overview-chart {
            width: 220px;
            height: 220px;
        }
    </style>
@endpush

@section('content')
    <div class="row" bp-section="crud-operation-list">
        <div class="{{ $crud->getListContentClass() }}">
            <div class="mb-4">
                <h2 class="mb-1">My Tasks</h2>
                <p class="text-muted mb-0">Your personal admin tasks across all time.</p>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-sm-6 col-xl-3">
                    <div class="card border-0 bg-primary text-white h-100">
                        <div class="card-body">
                            <div class="text-uppercase small fw-semibold">Total Personal Tasks</div>
                            <div class="display-6 fw-bold">{{ $stats['total_tasks'] }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="card border-0 bg-warning text-dark h-100">
                        <div class="card-body">
                            <div class="text-uppercase small fw-semibold">Pending / In Progress</div>
                            <div class="display-6 fw-bold">{{ $stats['open_tasks'] }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="card border-0 bg-info text-white h-100">
                        <div class="card-body">
                            <div class="text-uppercase small fw-semibold">Completed</div>
                            <div class="display-6 fw-bold">{{ $stats['completed_status'] }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="card border-0 bg-success text-white h-100">
                        <div class="card-body">
                            <div class="text-uppercase small fw-semibold">Approved Completed</div>
                            <div class="display-6 fw-bold">{{ $stats['completed'] }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="card border-0 bg-danger text-white h-100">
                        <div class="card-body">
                            <div class="text-uppercase small fw-semibold">Could Not Be Completed</div>
                            <div class="display-6 fw-bold">{{ $stats['could_not_be_achieved'] }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="card border-0 bg-primary text-white h-100">
                        <div class="card-body">
                            <div class="text-uppercase small fw-semibold">Completion Rate</div>
                            <div class="display-6 fw-bold">{{ number_format($stats['completion_rate'], 1) }}%</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <h4 class="mb-0">Task Status Distribution (All Time)</h4>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-column flex-md-row align-items-center gap-4">
                        <div class="position-relative my-task-overview-chart">
                            <svg viewBox="0 0 120 120" class="w-100 h-100">
                                <circle cx="60" cy="60" r="42" fill="none" stroke="#e9ecef" stroke-width="18"></circle>
                                @foreach($statusCounts as $statusCount)
                                    @php
                                        $count = $statusCount['count'];
                                        $segmentLength = $totalTasks > 0 ? ($count / $totalTasks) * $circumference : 0;
                                        $dashArray = $segmentLength.' '.max($circumference - $segmentLength, 0);
                                        $dashOffset = -$runningOffset;
                                        $runningOffset += $segmentLength;
                                    @endphp
                                    @if($count > 0)
                                        <circle
                                            cx="60"
                                            cy="60"
                                            r="42"
                                            fill="none"
                                            stroke="{{ $statusColors[$statusCount['status']] ?? '#6c757d' }}"
                                            stroke-width="18"
                                            stroke-dasharray="{{ $dashArray }}"
                                            stroke-dashoffset="{{ $dashOffset }}"
                                            transform="rotate(-90 60 60)"
                                            stroke-linecap="butt"
                                        ></circle>
                                    @endif
                                @endforeach
                            </svg>
                            <div class="position-absolute top-50 start-50 translate-middle text-center">
                                <div class="text-muted small text-uppercase">Tasks</div>
                                <div class="display-6 fw-bold">{{ $totalTasks }}</div>
                            </div>
                        </div>

                        <div class="w-100">
                            @foreach($statusCounts as $statusCount)
                                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="rounded-circle d-inline-block" style="width: 12px; height: 12px; background-color: {{ $statusColors[$statusCount['status']] ?? '#6c757d' }};"></span>
                                        <span>{{ $statusCount['label'] }}</span>
                                    </div>
                                    <span class="fw-semibold">{{ $statusCount['count'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            @include('admin.personal-tasks.partials.filters')

            <x-backpack::datatable :controller="$controller" :crud="$crud" :modifiesUrl="true" />
        </div>
    </div>
@endsection

@push('after_scripts')
    @include('vendor.backpack.crud.buttons.inc.delete_entry_script')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const clearButton = document.getElementById('clearMyTaskFiltersButton');

            if (!clearButton) {
                return;
            }

            clearButton.addEventListener('click', function (event) {
                event.preventDefault();

                const table = document.querySelector('table[id^="crudTable"]');
                const persistentTableSlug = table?.getAttribute('data-persistent-table-slug');
                const tableId = table?.id;

                if (persistentTableSlug) {
                    localStorage.removeItem(`${persistentTableSlug}_list_url`);
                    localStorage.removeItem(`${persistentTableSlug}_list_url_time`);
                }

                if (tableId) {
                    Object.keys(localStorage)
                        .filter((key) => key.startsWith(`DataTables_${tableId}`))
                        .forEach((key) => localStorage.removeItem(key));
                }

                window.location.href = clearButton.href;
            });
        });
    </script>
@endpush
