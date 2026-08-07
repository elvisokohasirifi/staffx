@php
    $hasActiveFilters = filled(request()->query('start_date'))
        || filled(request()->query('end_date'))
        || filled(request()->query('status'))
        || (backpack_user()?->isAdmin() && filled(request()->query('approval_status')))
        || (backpack_user()?->isAdmin() && filled(request()->query('staff_id')));
@endphp

<div class="mb-3">
    <button
        class="btn btn-outline-primary"
        type="button"
        data-bs-toggle="collapse"
        data-bs-target="#taskFiltersPanel"
        aria-expanded="{{ $hasActiveFilters ? 'true' : 'false' }}"
        aria-controls="taskFiltersPanel"
    >
        <i class="la la-filter me-1"></i>
        Filters
    </button>
</div>

<div class="collapse {{ $hasActiveFilters ? 'show' : '' }}" id="taskFiltersPanel">
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('tasks.index') }}" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label for="start_date" class="form-label">Start Date</label>
                    <input
                        type="date"
                        name="start_date"
                        id="start_date"
                        class="form-control"
                        value="{{ request()->query('start_date') }}"
                    >
                </div>
                <div class="col-md-4">
                    <label for="end_date" class="form-label">End Date</label>
                    <input
                        type="date"
                        name="end_date"
                        id="end_date"
                        class="form-control"
                        value="{{ request()->query('end_date') }}"
                    >
                </div>
                <div class="col-md-4">
                    <label for="status" class="form-label">Status</label>
                    <select name="status" id="status" class="form-select">
                        <option value="">All Statuses</option>
                        @foreach (\App\TaskStatus::options() as $value => $label)
                            <option value="{{ $value }}" @selected(request()->query('status') === $value)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
                @if (backpack_user()?->isAdmin())
                    <div class="col-md-4">
                        <label for="staff_id" class="form-label">Staff Member</label>
                        <select name="staff_id" id="staff_id" class="form-select">
                            <option value="">All Staff</option>
                            @foreach (\App\Models\User::query()->staff()->orderBy('name')->get() as $staffMember)
                                <option value="{{ $staffMember->getKey() }}" @selected(request()->query('staff_id') === $staffMember->getKey())>
                                    {{ $staffMember->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="approval_status" class="form-label">Completion Approval</label>
                        <select name="approval_status" id="approval_status" class="form-select">
                            <option value="">All Completion States</option>
                            <option value="pending" @selected(request()->query('approval_status') === 'pending')>
                                Pending Approval
                            </option>
                            <option value="approved" @selected(request()->query('approval_status') === 'approved')>
                                Approved Completed
                            </option>
                        </select>
                    </div>
                @endif
                <div class="col-md-{{ backpack_user()?->isAdmin() ? '12' : '4' }} d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Apply Filter</button>
                    <a href="{{ route('tasks.index') }}" class="btn btn-outline-secondary" id="clearTaskFiltersButton">Clear</a>
                </div>
            </form>
        </div>
    </div>
</div>
