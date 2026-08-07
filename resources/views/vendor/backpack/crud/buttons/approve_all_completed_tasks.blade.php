<form
    method="POST"
    action="{{ route('tasks.approve-completed-all') }}"
    style="display:inline"
    onsubmit="return confirm('Approve all pending completed tasks?');"
>
    @csrf
    <button type="submit" class="btn btn-outline-success">
        <i class="la la-check-double me-1"></i>
        Approve All Pending
    </button>
</form>
