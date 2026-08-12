@extends(backpack_view('blank'))

@section('content')
    <div class="mb-4">
        <h2 class="mb-1">Bulk Create My Tasks</h2>
        <p class="text-muted mb-0">Create several private admin tasks at once. Each new line becomes a separate task title.</p>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('my-tasks.bulk-store') }}">
                        @csrf

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

                        <div class="mb-4">
                            <label for="task_lines" class="form-label">Tasks</label>
                            <textarea
                                id="task_lines"
                                name="task_lines"
                                rows="10"
                                class="form-control @error('task_lines') is-invalid @enderror"
                                placeholder="Review branch reports&#10;Prepare tomorrow's outline&#10;Follow up on approvals"
                                required
                            >{{ old('task_lines') }}</textarea>
                            <div class="form-text">Enter one task title per line.</div>
                            @error('task_lines')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Create My Tasks</button>
                            <a href="{{ backpack_url('my-tasks') }}" class="btn btn-link">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
