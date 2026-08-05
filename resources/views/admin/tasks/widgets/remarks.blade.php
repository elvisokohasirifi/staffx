@php
    $task = $widget['task'];
@endphp

<div class="card mt-4">
    <div class="card-header">
        <h4 class="mb-0">Remarks & Responses</h4>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('tasks.remarks.store', $task->getKey()) }}" class="mb-4">
            @csrf
            <div class="mb-3">
                <label for="new-remark-body" class="form-label">Add a new remark</label>
                <textarea
                    id="new-remark-body"
                    name="body"
                    rows="3"
                    class="form-control @error('body') is-invalid @enderror"
                    placeholder="Write a remark or update..."
                >{{ old('body') }}</textarea>
                @error('body')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <button type="submit" class="btn btn-primary">Post Remark</button>
        </form>

        @forelse($task->remarks as $remark)
            @include('admin.tasks.widgets.partials.remark', ['remark' => $remark, 'task' => $task, 'depth' => 0])
        @empty
            <p class="text-muted mb-0">No remarks have been added to this task yet.</p>
        @endforelse
    </div>
</div>
