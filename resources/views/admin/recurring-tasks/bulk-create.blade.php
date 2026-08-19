@extends(backpack_view('blank'))

@section('content')
    <div class="mb-4">
        <h2 class="mb-1">Bulk Create Recurring Tasks</h2>
        <p class="text-muted mb-0">Create several recurring tasks for one staff member at once. Each new line becomes a separate recurring task title.</p>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('recurring-tasks.bulk-store') }}">
                        @csrf

                        <div class="mb-3">
                            <label for="assignee_id" class="form-label">Staff Member</label>
                            <select id="assignee_id" name="assignee_id" class="form-select @error('assignee_id') is-invalid @enderror" required>
                                <option value="">Select a staff member</option>
                                @foreach($staffMembers as $staffMember)
                                    <option value="{{ $staffMember->getKey() }}" @selected(old('assignee_id') === $staffMember->getKey())>
                                        {{ $staffMember->name }} ({{ $staffMember->email }})
                                    </option>
                                @endforeach
                            </select>
                            @error('assignee_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="scheduled_time" class="form-label">Scheduled Time</label>
                            <input
                                id="scheduled_time"
                                type="time"
                                name="scheduled_time"
                                value="{{ old('scheduled_time', '23:59') }}"
                                step="60"
                                class="form-control @error('scheduled_time') is-invalid @enderror"
                            >
                            @error('scheduled_time')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label d-block">Recurring Days</label>
                            <div class="row g-2">
                                @foreach($recurringDayOptions as $value => $label)
                                    <div class="col-sm-6 col-lg-4">
                                        <div class="form-check border rounded px-3 py-2 h-100">
                                            <input
                                                id="recurring_days_{{ $value }}"
                                                type="checkbox"
                                                name="recurring_days[]"
                                                value="{{ $value }}"
                                                class="form-check-input @if($errors->has('recurring_days') || $errors->has('recurring_days.*')) is-invalid @endif"
                                                @checked(in_array($value, old('recurring_days', \App\Models\RecurringTask::defaultRecurringDays()), true))
                                            >
                                            <label for="recurring_days_{{ $value }}" class="form-check-label">{{ $label }}</label>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            @if($errors->has('recurring_days'))
                                <div class="invalid-feedback d-block">{{ $errors->first('recurring_days') }}</div>
                            @elseif($errors->has('recurring_days.*'))
                                <div class="invalid-feedback d-block">{{ $errors->first('recurring_days.*') }}</div>
                            @endif
                        </div>

                        <div class="form-check mb-3">
                            <input
                                id="is_active"
                                type="checkbox"
                                name="is_active"
                                value="1"
                                class="form-check-input @error('is_active') is-invalid @enderror"
                                @checked(old('is_active', '1') === '1')
                            >
                            <label for="is_active" class="form-check-label">Active immediately</label>
                            @error('is_active')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="task_lines" class="form-label">Recurring Tasks</label>
                            <textarea
                                id="task_lines"
                                name="task_lines"
                                rows="10"
                                class="form-control @error('task_lines') is-invalid @enderror"
                                placeholder="Open morning attendance sheet&#10;Send devotion reminder&#10;Review team report"
                                required
                            >{{ old('task_lines') }}</textarea>
                            <div class="form-text">Enter one recurring task title per line.</div>
                            @error('task_lines')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Create Recurring Tasks</button>
                            <a href="{{ backpack_url('recurring-tasks') }}" class="btn btn-link">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
