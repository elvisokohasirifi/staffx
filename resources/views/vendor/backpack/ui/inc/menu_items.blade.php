
<x-backpack::menu-item title="Dashboard" icon="la la-home" :link="backpack_url('dashboard')" />
<x-backpack::menu-item title="Tasks" icon="la la-calendar-check" :link="backpack_url('tasks')" />
@if(backpack_user()?->isAdmin())
    <x-backpack::menu-item title="My Tasks" icon="la la-user-check" :link="backpack_url('my-tasks')" />
    <x-backpack::menu-item title="Recurring Tasks" icon="la la-sync" :link="backpack_url('recurring-tasks')" />
    <x-backpack::menu-item title="Email Notifications" icon="la la-envelope" :link="backpack_url('email-notifications')" />
    <li class="nav-item">
        <a class="nav-link" href="{{ route('tasks.index', ['status' => \App\TaskStatus::Completed->value, 'approval_status' => 'pending']) }}">
            <i class="nav-icon la la-check-circle d-block d-lg-none d-xl-block"></i>
            <span>Pending Approvals</span>
            @if (($pendingTaskApprovalCount ?? 0) > 0)
                <span class="badge ms-auto bg-danger">{{ $pendingTaskApprovalCount }}</span>
            @endif
        </a>
    </li>
    <x-backpack::menu-item :title="backpack_user()?->canManageAllUsers() ? 'Users' : 'Staff'" icon="la la-users" :link="backpack_url('staff')" />
    <x-backpack::menu-item title="Summary" icon="la la-chart-pie" :link="route('summary.index')" />
    @if(backpack_user()?->hasAdminEmailAccess())
        <x-backpack::menu-item title="Laravel Logs" icon="la la-file-alt" :link="route('log.index')" />
        <x-backpack::menu-item title="Backups" icon="la la-hdd-o" :link="route('backup.index')" />
        <x-backpack::menu-item title="Activity Logs" icon="la la-stream" :link="backpack_url('activity-log')" />
    @endif
@endif
