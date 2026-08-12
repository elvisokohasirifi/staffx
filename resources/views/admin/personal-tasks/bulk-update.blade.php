@extends(backpack_view('blank'))

@section('content')
    <div class="mb-4">
        <h2 class="mb-1">Bulk Update My Tasks</h2>
        <p class="text-muted mb-0">Filter your personal tasks, select the ones you want, then apply shared updates at once.</p>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('my-tasks.bulk-update') }}" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label for="filter_scheduled_for" class="form-label">Scheduled Date</label>
                    <input id="filter_scheduled_for" type="date" name="filter_scheduled_for" value="{{ $filters['filter_scheduled_for'] }}" class="form-control">
                </div>
                <div class="col-md-4">
                    <label for="filter_status" class="form-label">Status</label>
                    <select id="filter_status" name="filter_status" class="form-select">
                        <option value="">All Statuses</option>
                        @foreach($taskStatusOptions as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['filter_status'] ?? null) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="filter_approval_status" class="form-label">Approval Status</label>
                    <select id="filter_approval_status" name="filter_approval_status" class="form-select">
                        <option value="">All</option>
                        <option value="pending" @selected(($filters['filter_approval_status'] ?? null) === 'pending')>Pending Approval</option>
                        <option value="approved" @selected(($filters['filter_approval_status'] ?? null) === 'approved')>Approved Completed</option>
                    </select>
                </div>
                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="{{ route('my-tasks.bulk-update') }}" class="btn btn-outline-secondary">Clear</a>
                </div>
            </form>
        </div>
    </div>

    <form method="POST" action="{{ route('my-tasks.bulk-update-store') }}">
        @csrf

        <input type="hidden" name="filter_scheduled_for" value="{{ $filters['filter_scheduled_for'] }}">
        <input type="hidden" name="filter_status" value="{{ $filters['filter_status'] }}">
        <input type="hidden" name="filter_approval_status" value="{{ $filters['filter_approval_status'] }}">

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">Matching Tasks</h4>
                        <span class="text-muted small">{{ $tasks->count() }} found</span>
                    </div>
                    <div class="card-body p-0">
                        @if($tasks->isEmpty())
                            <div class="p-4 text-muted">No personal tasks match the current filters.</div>
                        @else
                            <div class="table-responsive">
                                <table class="table mb-0">
                                    <thead>
                                        <tr>
                                            <th style="width: 48px;"><input type="checkbox" id="select_all_tasks"></th>
                                            <th>Task</th>
                                            <th>Date</th>
                                            <th>Status</th>
                                            <th>Approved</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($tasks as $task)
                                            <tr>
                                                <td>
                                                    <input type="checkbox" name="task_ids[]" value="{{ $task->getKey() }}" class="bulk-task-checkbox" @checked(in_array($task->getKey(), old('task_ids', []), true))>
                                                </td>
                                                <td><div class="fw-semibold">{{ $task->title }}</div></td>
                                                <td>{{ $task->scheduled_for?->format('M j, Y') }}</td>
                                                <td>{{ $taskStatusOptions[$task->status->value] ?? $task->status->value }}</td>
                                                <td>{{ $task->approved_as_completed ? 'Yes' : 'No' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @error('task_ids')
                                <div class="text-danger small p-3">{{ $message }}</div>
                            @enderror
                            @error('task_ids.*')
                                <div class="text-danger small p-3 pt-0">{{ $message }}</div>
                            @enderror
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">Updates To Apply</h4>
                    </div>
                    <div class="card-body">
                        @error('bulk_update')
                            <div class="alert alert-danger">{{ $message }}</div>
                        @enderror

                        <div class="mb-3">
                            <label for="scheduled_for" class="form-label">Scheduled Date</label>
                            <input id="scheduled_for" type="date" name="scheduled_for" value="{{ old('scheduled_for') }}" class="form-control @error('scheduled_for') is-invalid @enderror">
                            @error('scheduled_for')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select id="status" name="status" class="form-select @error('status') is-invalid @enderror">
                                <option value="">Do not change</option>
                                @foreach($taskStatusOptions as $value => $label)
                                    <option value="{{ $value }}" @selected(old('status') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('status')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="approval_action" class="form-label">Approved Completed</label>
                            <select id="approval_action" name="approval_action" class="form-select @error('approval_action') is-invalid @enderror">
                                <option value="">Do not change</option>
                                <option value="approve" @selected(old('approval_action') === 'approve')>Mark as approved</option>
                                <option value="unapprove" @selected(old('approval_action') === 'unapprove')>Remove approval</option>
                            </select>
                            <div class="form-text">Approvals only count when the task status is completed.</div>
                            @error('approval_action')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary" @disabled($tasks->isEmpty())>Update Selected Tasks</button>
                            <a href="{{ backpack_url('my-tasks') }}" class="btn btn-link">Cancel</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection

@push('after_scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const selectAll = document.getElementById('select_all_tasks');
            const checkboxes = document.querySelectorAll('.bulk-task-checkbox');

            if (!selectAll || checkboxes.length === 0) {
                return;
            }

            selectAll.addEventListener('change', function () {
                checkboxes.forEach(function (checkbox) {
                    checkbox.checked = selectAll.checked;
                });
            });
        });
    </script>
@endpush
