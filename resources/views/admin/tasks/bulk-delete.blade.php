@extends(backpack_view('blank'))

@section('content')
    <div class="mb-4">
        <h2 class="mb-1">Bulk Delete Tasks</h2>
        <p class="text-muted mb-0">Filter tasks, select the ones you want removed, then delete them in one action.</p>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('tasks.bulk-delete') }}" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="filter_assignee_id" class="form-label">Staff Member</label>
                    <select id="filter_assignee_id" name="filter_assignee_id" class="form-select">
                        <option value="">All Staff</option>
                        @foreach($staffMembers as $staffMember)
                            <option value="{{ $staffMember->getKey() }}" @selected(($filters['filter_assignee_id'] ?? null) === $staffMember->getKey())>
                                {{ $staffMember->name }} ({{ $staffMember->email }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="filter_scheduled_for" class="form-label">Scheduled Date</label>
                    <input
                        id="filter_scheduled_for"
                        type="date"
                        name="filter_scheduled_for"
                        value="{{ $filters['filter_scheduled_for'] }}"
                        class="form-control"
                    >
                </div>
                <div class="col-md-3">
                    <label for="filter_status" class="form-label">Status</label>
                    <select id="filter_status" name="filter_status" class="form-select">
                        <option value="">All Statuses</option>
                        @foreach($taskStatusOptions as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['filter_status'] ?? null) === $value)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="filter_approval_status" class="form-label">Approval Status</label>
                    <select id="filter_approval_status" name="filter_approval_status" class="form-select">
                        <option value="">All</option>
                        <option value="pending" @selected(($filters['filter_approval_status'] ?? null) === 'pending')>Pending Approval</option>
                        <option value="approved" @selected(($filters['filter_approval_status'] ?? null) === 'approved')>Approved Completed</option>
                    </select>
                </div>
                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="{{ route('tasks.bulk-delete') }}" class="btn btn-outline-secondary">Clear</a>
                </div>
            </form>
        </div>
    </div>

    <form method="POST" action="{{ route('tasks.bulk-delete-destroy') }}" onsubmit="return confirm('Delete the selected tasks? This cannot be undone.');">
        @csrf

        <input type="hidden" name="filter_assignee_id" value="{{ $filters['filter_assignee_id'] }}">
        <input type="hidden" name="filter_scheduled_for" value="{{ $filters['filter_scheduled_for'] }}">
        <input type="hidden" name="filter_status" value="{{ $filters['filter_status'] }}">
        <input type="hidden" name="filter_approval_status" value="{{ $filters['filter_approval_status'] }}">

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Matching Tasks</h4>
                <span class="text-muted small">{{ $tasks->count() }} found</span>
            </div>
            <div class="card-body p-0">
                @if($tasks->isEmpty())
                    <div class="p-4 text-muted">No tasks match the current filters.</div>
                @else
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 48px;">
                                        <input type="checkbox" id="select_all_tasks">
                                    </th>
                                    <th>Task</th>
                                    <th>Staff</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Approved</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($tasks as $task)
                                    <tr>
                                        <td>
                                            <input
                                                type="checkbox"
                                                name="task_ids[]"
                                                value="{{ $task->getKey() }}"
                                                class="bulk-task-checkbox"
                                                @checked(in_array($task->getKey(), old('task_ids', []), true))
                                            >
                                        </td>
                                        <td>
                                            <div class="fw-semibold">{{ $task->title }}</div>
                                        </td>
                                        <td>{{ $task->assignee?->name }}</td>
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
            <div class="card-footer d-flex gap-2">
                <button type="submit" class="btn btn-danger" @disabled($tasks->isEmpty())>Delete Selected Tasks</button>
                <a href="{{ backpack_url('tasks') }}" class="btn btn-link">Cancel</a>
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
