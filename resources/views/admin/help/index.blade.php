@extends(backpack_view('blank'))

@php
    $defaultBreadcrumbs = [
        trans('backpack::crud.admin') => url(config('backpack.base.route_prefix'), 'dashboard'),
        'Help' => false,
    ];

    $breadcrumbs = $breadcrumbs ?? $defaultBreadcrumbs;
@endphp

@section('content')
    <div class="row">
        <div class="col-xl-10">
            <div class="mb-4">
                <h2 class="mb-1">Help Center</h2>
                <p class="text-muted mb-0">
                    This guide shows the features available to you as {{ $user->isAdmin() ? 'an admin' : 'a staff member' }}.
                </p>
            </div>

            <div class="alert alert-info d-flex gap-2 align-items-start" role="alert">
                <i class="la la-info-circle fs-4"></i>
                <div>
                    <strong>How task completion works:</strong> staff can mark a task as completed, but it counts as completed in reports only after an admin approves it. Until then, it is treated as pending in summaries.
                </div>
            </div>

            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="card h-100">
                        <div class="card-header">
                            <h4 class="mb-0"><i class="la la-home me-2"></i>Dashboard</h4>
                        </div>
                        <div class="card-body">
                            <p>Your dashboard is the fastest way to see what needs attention.</p>
                            @if ($user->isAdmin())
                                <ul class="mb-0">
                                    <li>Use the cards to review today's task totals by status.</li>
                                    <li>Review the full list of tasks due today and open any task for its details, remarks, or changes.</li>
                                    <li>Use the Pending Approvals link in the sidebar to review staff-completed tasks awaiting approval.</li>
                                </ul>
                            @else
                                <ul class="mb-0">
                                    <li>The cards show your totals for today, including completed, approved completed, pending or in-progress, and tasks that could not be completed.</li>
                                    <li>The list below the cards shows all your pending and in-progress tasks, including older unfinished work.</li>
                                    <li>Select <strong>Start</strong> for a pending task, then <strong>Complete</strong> once it is finished.</li>
                                </ul>
                            @endif
                        </div>
                        <div class="card-footer bg-transparent">
                            <a href="{{ backpack_url('dashboard') }}" class="btn btn-outline-primary">Open Dashboard</a>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card h-100">
                        <div class="card-header">
                            <h4 class="mb-0"><i class="la la-calendar-check me-2"></i>Tasks</h4>
                        </div>
                        <div class="card-body">
                            <ul class="mb-0">
                                <li>Open <strong>Tasks</strong> to browse the work assigned to you.</li>
                                <li>Select <strong>Filters</strong> to narrow tasks by date range or status. Admins can also filter by staff member and completion approval.</li>
                                <li>Open a task to read its full title, description, schedule, status, and conversation.</li>
                                <li>Add a remark or response on the task page. Remarks can have multiple responses from both staff and admins.</li>
                                @if ($user->isStaff())
                                    <li>Only your own tasks are visible. You cannot create, reassign, or delete tasks.</li>
                                    <li>Use the task page or list actions to start a pending task and complete an in-progress task. Use Edit to report a task that could not be achieved and add outcome notes.</li>
                                @else
                                    <li>Use Create to add one task, or Bulk Create Tasks to assign one newline-separated list of tasks to one staff member for a chosen date and time.</li>
                                    <li>Use Edit to change a task's schedule, staff member, details, status, or completion approval. Delete removes a task.</li>
                                @endif
                            </ul>
                        </div>
                        <div class="card-footer bg-transparent">
                            <a href="{{ backpack_url('tasks') }}" class="btn btn-outline-primary">Open Tasks</a>
                        </div>
                    </div>
                </div>

                @if ($user->isAdmin())
                    <div class="col-lg-6">
                        <div class="card h-100">
                            <div class="card-header">
                                <h4 class="mb-0"><i class="la la-check-circle me-2"></i>Approvals and Bulk Changes</h4>
                            </div>
                            <div class="card-body">
                                <ul class="mb-0">
                                    <li>When staff mark work completed, it appears as Pending Approval until you approve it.</li>
                                    <li>Filter Tasks by <strong>Completion Approval: Pending Approval</strong>, then approve individual items or use Approve All Completed Tasks.</li>
                                    <li>Use Bulk Approve Completed Tasks to select several completed tasks and approve them together.</li>
                                    <li>Use Bulk Update Tasks to update selected tasks in one action, including reassignment, status, scheduling, and approval. Use Bulk Delete Tasks to remove selected tasks.</li>
                                    <li>Staff receive grouped notifications when bulk assignment or approval affects several of their tasks.</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="card h-100">
                            <div class="card-header">
                                <h4 class="mb-0"><i class="la la-user-check me-2"></i>My Tasks</h4>
                            </div>
                            <div class="card-body">
                                <p>My Tasks is your private task list as an admin. These tasks are visible only to you and never appear in staff summaries.</p>
                                <ul class="mb-0">
                                    <li>Create one private task or use Bulk Create My Tasks to add one task title per line.</li>
                                    <li>Filter by date range or status, then use the bulk tools to update or delete selected private tasks.</li>
                                    <li>Start and complete private tasks from the list or task page. The cards and pie chart show your all-time private task progress.</li>
                                </ul>
                            </div>
                            <div class="card-footer bg-transparent">
                                <a href="{{ backpack_url('my-tasks') }}" class="btn btn-outline-primary">Open My Tasks</a>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="card h-100">
                            <div class="card-header">
                                <h4 class="mb-0"><i class="la la-sync me-2"></i>Recurring Tasks</h4>
                            </div>
                            <div class="card-body">
                                <ul class="mb-0">
                                    <li>Create a recurring task for a staff member and select the exact days of the week on which it should repeat.</li>
                                    <li>Set a scheduled time and turn a recurring task active or inactive as needed.</li>
                                    <li>Use Bulk Create Recurring Tasks to add multiple titles for the same staff member and the same selected days.</li>
                                    <li>The system creates the scheduled staff tasks automatically each day and avoids creating duplicates.</li>
                                </ul>
                            </div>
                            <div class="card-footer bg-transparent">
                                <a href="{{ backpack_url('recurring-tasks') }}" class="btn btn-outline-primary">Open Recurring Tasks</a>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="card h-100">
                            <div class="card-header">
                                <h4 class="mb-0"><i class="la la-chart-pie me-2"></i>Summary and Email</h4>
                            </div>
                            <div class="card-body">
                                <ul class="mb-0">
                                    <li>Open Summary to see task totals, approved completion rates per staff member, and a status distribution chart.</li>
                                    <li>Apply a date range to focus the summary. With no dates selected, it shows all time.</li>
                                    <li>Open Email Notifications to send a message to all staff, all admins, or selected individual users. The sent-notification list records recipients and send time.</li>
                                    <li>The system also sends reminders at 3:00 PM and 8:00 PM to staff who still have incomplete tasks due that day.</li>
                                </ul>
                            </div>
                            <div class="card-footer bg-transparent d-flex flex-wrap gap-2">
                                <a href="{{ route('summary.index') }}" class="btn btn-outline-primary">Open Summary</a>
                                <a href="{{ backpack_url('email-notifications') }}" class="btn btn-outline-primary">Open Email Notifications</a>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="card h-100">
                            <div class="card-header">
                                <h4 class="mb-0"><i class="la la-users me-2"></i>Staff Accounts</h4>
                            </div>
                            <div class="card-body">
                                <ul class="mb-0">
                                    <li>Open Staff to add staff members with their name and email address.</li>
                                    <li>A random password is created automatically and the staff member receives a password-reset email to choose their own password.</li>
                                    <li>Open View Tasks next to a staff member to see that person's assigned work.</li>
                                    <li>Use Impersonate to temporarily view the app as another user. Select Stop Impersonating in the sidebar to return to your own account.</li>
                                </ul>
                            </div>
                            <div class="card-footer bg-transparent">
                                <a href="{{ backpack_url('staff') }}" class="btn btn-outline-primary">Open Staff</a>
                            </div>
                        </div>
                    </div>

                    @if ($user->hasAdminEmailAccess())
                        <div class="col-lg-6">
                            <div class="card h-100 border-warning">
                                <div class="card-header">
                                    <h4 class="mb-0"><i class="la la-shield me-2"></i>Restricted Administrator Tools</h4>
                                </div>
                                <div class="card-body">
                                    <ul class="mb-0">
                                        <li>Your Staff page is labelled Users because you can view and edit every user, including admins. You can change name, email, password, and role.</li>
                                        <li>Laravel Logs shows the application and outgoing-mail logs for troubleshooting.</li>
                                        <li>Backups lets you run and retrieve backups. The database backup job also runs daily.</li>
                                        <li>Activity Logs records changes to users and tasks. Activity buttons appear only for this configured account.</li>
                                    </ul>
                                </div>
                                <div class="card-footer bg-transparent d-flex flex-wrap gap-2">
                                    <a href="{{ backpack_url('staff') }}" class="btn btn-outline-warning">Open Users</a>
                                    <a href="{{ route('log.index') }}" class="btn btn-outline-warning">Laravel Logs</a>
                                    <a href="{{ route('backup.index') }}" class="btn btn-outline-warning">Backups</a>
                                    <a href="{{ backpack_url('activity-log') }}" class="btn btn-outline-warning">Activity Logs</a>
                                </div>
                            </div>
                        </div>
                    @endif
                @endif

                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="mb-0"><i class="la la-lock me-2"></i>Signing In and Account Access</h4>
                        </div>
                        <div class="card-body">
                            <ul class="mb-0">
                                <li>Use your email address and password to sign in. If you do not know your password, use Forgot Password on the sign-in page.</li>
                                <li>If Google sign-in is available on the sign-in page, you can use it only with the email address already registered for your account.</li>
                                <li>New accounts are created by an admin. If you cannot access the app, contact an admin to check your account and email address.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
