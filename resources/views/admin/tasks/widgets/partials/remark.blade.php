@php
    $marginClass = match (true) {
        $depth >= 2 => 'ms-5',
        $depth === 1 => 'ms-4',
        default => '',
    };
@endphp

<div class="border rounded p-3 mb-3 {{ $marginClass }}">
    <div class="d-flex justify-content-between align-items-start gap-3 mb-2">
        <div>
            <strong>{{ $remark->author?->name }}</strong>
            <span class="badge {{ $remark->is_admin_remark ? 'bg-primary' : 'bg-secondary' }} ms-2">
                {{ $remark->is_admin_remark ? 'Admin' : 'Staff' }}
            </span>
        </div>
        <small class="text-muted">{{ $remark->created_at?->format('M j, Y g:i A') }}</small>
    </div>

    <div class="mb-3">{!! nl2br(e($remark->body)) !!}</div>

    <form method="POST" action="{{ route('tasks.remarks.store', $task->getKey()) }}" class="mb-2">
        @csrf
        <input type="hidden" name="parent_remark_id" value="{{ $remark->getKey() }}">
        <div class="mb-2">
            <label class="form-label">Add a response</label>
            <textarea
                name="body"
                rows="2"
                class="form-control"
                placeholder="Reply to this remark..."
            ></textarea>
        </div>
        <button type="submit" class="btn btn-sm btn-outline-primary">Reply</button>
    </form>

    @foreach($remark->responses as $response)
        @include('admin.tasks.widgets.partials.remark', ['remark' => $response, 'task' => $task, 'depth' => $depth + 1])
    @endforeach
</div>
