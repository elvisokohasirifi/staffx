@php
    $hasActiveFilters = filled(request()->query('staff_id'))
        || filled(request()->query('repeat_pattern'))
        || request()->query('active') !== null;

    $staffMembers = \App\Models\User::query()->staff()->orderBy('name')->get();
@endphp

<div class="mb-3">
    <button
        class="btn btn-outline-primary"
        type="button"
        data-bs-toggle="collapse"
        data-bs-target="#recurringTaskFiltersPanel"
        aria-expanded="{{ $hasActiveFilters ? 'true' : 'false' }}"
        aria-controls="recurringTaskFiltersPanel"
    >
        <i class="la la-filter me-1"></i>
        Filters
    </button>
</div>

<div class="collapse {{ $hasActiveFilters ? 'show' : '' }}" id="recurringTaskFiltersPanel">
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('recurring-tasks.index') }}" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label for="staff_id" class="form-label">Staff Member</label>
                    <select name="staff_id" id="staff_id" class="form-select">
                        <option value="">All Staff</option>
                        @foreach ($staffMembers as $staffMember)
                            <option value="{{ $staffMember->getKey() }}" @selected(request()->query('staff_id') === $staffMember->getKey())>
                                {{ $staffMember->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="repeat_pattern" class="form-label">Repeat Pattern</label>
                    <select name="repeat_pattern" id="repeat_pattern" class="form-select">
                        <option value="">All Patterns</option>
                        @foreach (\App\RecurringTaskPattern::options() as $value => $label)
                            <option value="{{ $value }}" @selected(request()->query('repeat_pattern') === $value)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="active" class="form-label">Active Status</label>
                    <select name="active" id="active" class="form-select">
                        <option value="">All</option>
                        <option value="1" @selected(request()->query('active') === '1')>Active</option>
                        <option value="0" @selected(request()->query('active') === '0')>Inactive</option>
                    </select>
                </div>
                <div class="col-md-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Apply Filter</button>
                    <a href="{{ route('recurring-tasks.index') }}" class="btn btn-outline-secondary" id="clearRecurringTaskFiltersButton">Clear</a>
                </div>
            </form>
        </div>
    </div>
</div>
