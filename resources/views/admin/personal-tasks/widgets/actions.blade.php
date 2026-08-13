@php
    /** @var \App\Models\Task $task */
    $task = $widget['task'];
@endphp

@if(backpack_user()?->isAdmin() && in_array($task->status, [\App\TaskStatus::Pending, \App\TaskStatus::InProgress], true))
    <div class="card mb-4">
        <div class="card-body d-flex flex-wrap gap-2 align-items-center">
            <strong class="me-2">Task Actions</strong>

            @if($task->status === \App\TaskStatus::Pending)
                <form method="POST" action="{{ route('my-tasks.mark-in-progress', $task->getKey()) }}" class="m-0">
                    @csrf
                    <button type="submit" class="btn btn-primary">Mark as In Progress</button>
                </form>
            @elseif($task->status === \App\TaskStatus::InProgress)
                <form method="POST" action="{{ route('my-tasks.mark-completed', $task->getKey()) }}" class="m-0">
                    @csrf
                    <button type="submit" class="btn btn-success">Mark as Completed</button>
                </form>
            @endif
        </div>
    </div>
@endif
