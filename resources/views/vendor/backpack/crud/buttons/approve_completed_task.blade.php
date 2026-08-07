@if (backpack_user()?->isAdmin() && $entry->status === \App\TaskStatus::Completed && ! $entry->approved_as_completed)
    <form method="POST" action="{{ route('tasks.approve-completed', $entry->getKey()) }}" style="display:inline">
        @csrf
        <button type="submit" class="btn btn-sm btn-link" title="Approve completed task">
            <i class="la la-check-circle"></i> Approve
        </button>
    </form>
@endif
