@extends(backpack_view('blank'))

@push('after_styles')
    <style>
        .summary-table-column {
            min-width: 0;
        }

        .summary-table-card {
            min-width: 0;
        }

        .summary-table-card .card-body {
            min-width: 0;
        }

        .mobile-table-scroll {
            display: block;
            width: 100%;
            max-width: 100%;
            overflow-x: auto;
            overflow-y: hidden;
            -webkit-overflow-scrolling: touch;
            overscroll-behavior-x: contain;
        }

        .staff-summary-table-inner {
            min-width: 760px;
        }

        .staff-summary-table {
            width: 100%;
            margin-bottom: 0;
        }

        .staff-summary-mobile-card {
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        .staff-summary-mobile-card:last-child {
            border-bottom: 0;
        }
    </style>
@endpush

@php
    $defaultBreadcrumbs = [
        trans('backpack::crud.admin') => url(config('backpack.base.route_prefix'), 'dashboard'),
        'Summary' => false,
    ];

    $breadcrumbs = $breadcrumbs ?? $defaultBreadcrumbs;

    $statusColors = [
        'pending' => '#f0ad4e',
        'in_progress' => '#5bc0de',
        'completed' => '#5cb85c',
        'could_not_be_achieved' => '#d9534f',
    ];

    $circumference = 2 * pi() * 42;
    $runningOffset = 0;
    $statusDateRangeLabel = match (true) {
        filled($startDate) && filled($endDate) => \Illuminate\Support\Carbon::parse($startDate)->format('M j, Y').' - '.\Illuminate\Support\Carbon::parse($endDate)->format('M j, Y'),
        filled($startDate) => 'From '.\Illuminate\Support\Carbon::parse($startDate)->format('M j, Y'),
        filled($endDate) => 'Up to '.\Illuminate\Support\Carbon::parse($endDate)->format('M j, Y'),
        default => 'All Time',
    };
@endphp

@section('content')
    <div class="row">
        <div class="col-md-12">
            <div class="mb-4">
                <h2 class="mb-1">Task Summary</h2>
                <p class="text-muted mb-0">A staff-by-staff overview of assigned work, completion, and current status distribution.</p>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="{{ route('summary.index') }}" class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label for="start_date" class="form-label">Start Date</label>
                            <input type="date" name="start_date" id="start_date" class="form-control" value="{{ $startDate }}">
                        </div>
                        <div class="col-md-4">
                            <label for="end_date" class="form-label">End Date</label>
                            <input type="date" name="end_date" id="end_date" class="form-control" value="{{ $endDate }}">
                        </div>
                        <div class="col-md-4 d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Apply Filter</button>
                            <a href="{{ route('summary.index') }}" class="btn btn-outline-secondary">Clear</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-lg-5">
                    <div class="card h-100">
                        <div class="card-header">
                            <h4 class="mb-0">Task Status Distribution ({{ $statusDateRangeLabel }})</h4>
                        </div>
                        <div class="card-body">
                            <div class="d-flex flex-column flex-md-row align-items-center gap-4">
                                <div class="position-relative" style="width: 220px; height: 220px;">
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
                </div>

                <div class="col-lg-7 summary-table-column">
                    <div class="card h-100 summary-table-card">
                        <div class="card-header">
                            <h4 class="mb-0">Staff Summary</h4>
                        </div>
                        <div class="card-body p-0">
                            @if($staffSummaries->isEmpty())
                                <div class="p-4 text-muted">There are no staff members to summarize.</div>
                            @else
                                <div class="d-block d-xl-none">
                                    @foreach($staffSummaries as $summary)
                                        <div class="staff-summary-mobile-card p-3">
                                            <div class="fw-semibold">{{ $summary['staff']->name }}</div>
                                            <div class="text-muted small mb-3">{{ $summary['staff']->email }}</div>

                                            <div class="row g-3">
                                                <div class="col-6">
                                                    <div class="text-uppercase small text-muted">Assigned</div>
                                                    <div class="fw-semibold">{{ $summary['assigned_count'] }}</div>
                                                </div>
                                                <div class="col-6">
                                                    <div class="text-uppercase small text-muted">Completed</div>
                                                    <div class="fw-semibold">{{ $summary['completed_count'] }}</div>
                                                </div>
                                                <div class="col-6">
                                                    <div class="text-uppercase small text-muted">Pending</div>
                                                    <div class="fw-semibold">{{ $summary['pending_count'] }}</div>
                                                </div>
                                                <div class="col-6">
                                                    <div class="text-uppercase small text-muted">Could Not Be Completed</div>
                                                    <div class="fw-semibold">{{ $summary['could_not_be_achieved_count'] }}</div>
                                                </div>
                                                <div class="col-12">
                                                    <div class="text-uppercase small text-muted">Completion Rate</div>
                                                    <div class="fw-semibold">{{ number_format($summary['completion_rate'], 1) }}%</div>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>

                                <div class="table-responsive mobile-table-scroll d-none d-xl-block">
                                    <div class="staff-summary-table-inner">
                                        <table class="table staff-summary-table">
                                            <thead>
                                                <tr>
                                                    <th>Staff</th>
                                                    <th>Assigned</th>
                                                    <th>Completed</th>
                                                    <th>Pending</th>
                                                    <th>Could Not Be Completed</th>
                                                    <th>Completion Rate</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($staffSummaries as $summary)
                                                    <tr>
                                                        <td>
                                                            <div class="fw-semibold">{{ $summary['staff']->name }}</div>
                                                            <div class="text-muted small">{{ $summary['staff']->email }}</div>
                                                        </td>
                                                        <td>{{ $summary['assigned_count'] }}</td>
                                                        <td>{{ $summary['completed_count'] }}</td>
                                                        <td>{{ $summary['pending_count'] }}</td>
                                                        <td>{{ $summary['could_not_be_achieved_count'] }}</td>
                                                        <td>{{ number_format($summary['completion_rate'], 1) }}%</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
