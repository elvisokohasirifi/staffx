<a
    href="{{ route('tasks.bulk-update', ['filter_status' => \App\TaskStatus::Completed->value, 'filter_approval_status' => 'pending']) }}"
    class="btn btn-outline-success"
>
    <i class="la la-check-square"></i> Bulk Approve Completed Tasks
</a>
