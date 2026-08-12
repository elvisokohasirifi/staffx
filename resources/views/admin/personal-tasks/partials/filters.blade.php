@php
    $hasActiveFilters = filled(request()->query('start_date'))
        || filled(request()->query('end_date'))
        || filled(request()->query('status'));
@endphp

<div class="mb-3">
    <button
        class="btn btn-outline-primary"
        type="button"
        data-bs-toggle="collapse"
        data-bs-target="#myTaskFiltersPanel"
        aria-expanded="{{ $hasActiveFilters ? 'true' : 'false' }}"
        aria-controls="myTaskFiltersPanel"
    >
        <i class="la la-filter me-1"></i>
        Filters
    </button>
</div>

<div class="collapse {{ $hasActiveFilters ? 'show' : '' }}" id="myTaskFiltersPanel">
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('my-tasks.index') }}" class="row g-3 align-items-end">
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
                <div class="col-md-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Apply Filter</button>
                    <a href="{{ route('my-tasks.index') }}" class="btn btn-outline-secondary" id="clearMyTaskFiltersButton">Clear</a>
                </div>
            </form>
        </div>
    </div>
</div>
