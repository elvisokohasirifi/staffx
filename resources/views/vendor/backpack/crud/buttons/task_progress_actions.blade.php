@php
    /** @var \App\Models\Task $entry */
@endphp

@if(backpack_user()?->isStaff() && $entry->status === \App\TaskStatus::Pending)
    <form method="POST" action="{{ route('tasks.mark-in-progress', $entry->getKey()) }}" class="d-inline">
        @csrf
        <button type="submit" class="btn btn-sm btn-link" bp-button="task-mark-in-progress">
            <i class="la la-play-circle"></i> <span>Start</span>
        </button>
    </form>
@elseif(backpack_user()?->isStaff() && $entry->status === \App\TaskStatus::InProgress)
    <form method="POST" action="{{ route('tasks.mark-completed', $entry->getKey()) }}" class="d-inline">
        @csrf
        <button type="submit" class="btn btn-sm btn-link text-success" bp-button="task-mark-completed">
            <i class="la la-check-circle"></i> <span>Complete</span>
        </button>
    </form>
@endif
