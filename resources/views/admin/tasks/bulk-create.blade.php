@extends(backpack_view('blank'))

@section('content')
    <div class="mb-4">
        <h2 class="mb-1">Bulk Create Tasks</h2>
        <p class="text-muted mb-0">Assign several tasks to one staff member at once. Each new line becomes a separate task title.</p>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('tasks.bulk-store') }}">
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
                            <label for="scheduled_for" class="form-label">Scheduled Date</label>
                            <input
                                id="scheduled_for"
                                type="date"
                                name="scheduled_for"
                                value="{{ old('scheduled_for', today()->toDateString()) }}"
                                class="form-control @error('scheduled_for') is-invalid @enderror"
                                required
                            >
                            @error('scheduled_for')
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

                        <div class="mb-4">
                            <label for="task_lines" class="form-label">Tasks</label>
                            <textarea
                                id="task_lines"
                                name="task_lines"
                                rows="10"
                                class="form-control @error('task_lines') is-invalid @enderror"
                                placeholder="Open shop&#10;Check inventory&#10;Send report"
                                required
                            >{{ old('task_lines') }}</textarea>
                            <div class="form-text">Enter one task title per line.</div>
                            @error('task_lines')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Create Tasks</button>
                            <a href="{{ backpack_url('tasks') }}" class="btn btn-link">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
