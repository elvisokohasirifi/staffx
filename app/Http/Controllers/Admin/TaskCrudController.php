<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Tasks\SendTaskNotificationsAction;
use App\Http\Requests\TaskRequest;
use App\Models\Task;
use App\Models\TaskRemark;
use App\Models\User;
use App\TaskStatus;
use App\UserRole;
use Backpack\ActivityLog\Http\Controllers\Operations\EntryActivityOperation;
use Backpack\ActivityLog\Http\Controllers\Operations\ModelActivityOperation;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanel;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Backpack\CRUD\app\Library\Widget;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * @property-read CrudPanel $crud
 */
class TaskCrudController extends CrudController
{
    use CreateOperation;
    use DeleteOperation;
    use EntryActivityOperation {
        setupEntryActivityOperationDefaults as protected traitSetupEntryActivityOperationDefaults;
    }
    use ListOperation;
    use ModelActivityOperation {
        setupModelActivityOperationDefaults as protected traitSetupModelActivityOperationDefaults;
    }
    use ShowOperation {
        show as traitShow;
    }
    use UpdateOperation {
        edit as traitEdit;
        update as traitUpdate;
    }

    public function __construct(private SendTaskNotificationsAction $sendTaskNotifications)
    {
        parent::__construct();
    }

    public function setup(): void
    {
        CRUD::setModel(Task::class);
        CRUD::setRoute(trim((string) config('backpack.base.route_prefix'), '/').'/tasks');
        CRUD::setEntityNameStrings('task', 'tasks');
        CRUD::with(['admin', 'assignee']);
        CRUD::addClause('where', 'is_admin_personal', false);

        $this->crud->query->withCount('remarks')->orderBy('scheduled_for')->orderBy('scheduled_time')->orderBy('sort_order');

        $this->denyAllAccess();

        if (backpack_user()?->isAdmin()) {
            CRUD::allowAccess('list');
            CRUD::allowAccess('show');
            CRUD::allowAccess('create');
            CRUD::allowAccess('update');
            CRUD::allowAccess('delete');
        } elseif (backpack_user()?->isStaff()) {
            CRUD::allowAccess('list');
            CRUD::allowAccess('show');
            CRUD::allowAccess('update');
            CRUD::addClause('where', 'assignee_id', backpack_user()->getKey());
            CRUD::setAccessCondition('show', fn (Task $task): bool => backpack_user()->can('view', $task));
            CRUD::setAccessCondition('update', fn (Task $task): bool => backpack_user()->can('update', $task));
        }

        if ($this->canViewActivityButtons()) {
            CRUD::allowAccess('logsActivityOperation');
        } else {
            CRUD::denyAccess('logsActivityOperation');
        }
    }

    protected function setupListOperation(): void
    {
        $this->hideActivityButtonsWhenUnauthorized();
        $this->applyTaskFilters();
        CRUD::setListView('admin.tasks.list');

        if (backpack_user()?->isAdmin()) {
            CRUD::addButtonFromView('top', 'bulk_create_tasks', 'vendor.backpack.crud.buttons.bulk_create_tasks', 'end');
            CRUD::addButtonFromView('top', 'bulk_delete_tasks', 'vendor.backpack.crud.buttons.bulk_delete_tasks', 'end');
            CRUD::addButtonFromView('top', 'bulk_update_tasks', 'vendor.backpack.crud.buttons.bulk_update_tasks', 'end');
            CRUD::addButtonFromView('top', 'bulk_approve_completed_tasks', 'vendor.backpack.crud.buttons.bulk_approve_completed_tasks', 'end');
            if (request()->query('approval_status') === 'pending') {
                CRUD::addButtonFromView('top', 'approve_all_completed_tasks', 'vendor.backpack.crud.buttons.approve_all_completed_tasks', 'end');
            }
            CRUD::addButtonFromView('line', 'approve_completed_task', 'vendor.backpack.crud.buttons.approve_completed_task', 'beginning');
        }

        CRUD::addColumn([
            'name' => 'title',
            'label' => 'Task',
            'type' => 'text',
            'limit' => 10000,
            'wrapper' => [
                'style' => 'white-space: normal; word-break: break-word; min-width: 260px;',
            ],
        ]);
        CRUD::column('scheduled_for')->label('Date')->type('date');
        CRUD::addColumn([
            'name' => 'scheduled_time_label',
            'label' => 'Time',
            'type' => 'text',
            'value' => fn (Task $task): string => $task->scheduledTimeLabel(),
        ]);
        CRUD::addColumn([
            'name' => 'status_label',
            'label' => 'Status',
            'type' => 'text',
            'value' => fn (Task $task): string => TaskStatus::options()[$task->status->value] ?? $task->status->value,
        ]);
        CRUD::column('approved_as_completed')
            ->label('Approved Completed')
            ->type('boolean');
        CRUD::column('remarks_count')->label('Remarks')->type('number');

        if (backpack_user()?->isAdmin()) {
            CRUD::addColumn([
                'name' => 'assignee_id',
                'label' => 'Staff',
                'type' => 'select',
                'entity' => 'assignee',
                'attribute' => 'name',
                'model' => User::class,
            ]);
            CRUD::addColumn([
                'name' => 'admin_id',
                'label' => 'Created By',
                'type' => 'select',
                'entity' => 'admin',
                'attribute' => 'name',
                'model' => User::class,
            ]);
        } elseif (backpack_user()?->isStaff()) {
            CRUD::button('task_progress_actions')
                ->stack('line')
                ->view('vendor.backpack.crud.buttons.task_progress_actions');
        }
    }

    protected function setupCreateOperation(): void
    {
        CRUD::setValidation(TaskRequest::class);

        if (backpack_user()?->isAdmin()) {
            $this->addAdminTaskFields();
        } else {
            CRUD::field('status')->label('Status')->type('select_from_array')->options([
                TaskStatus::InProgress->value => 'In Progress',
                TaskStatus::Completed->value => 'Completed',
                TaskStatus::CouldNotBeAchieved->value => 'Could Not Be Achieved',
            ]);
            CRUD::field('outcome_notes')->label('Update Notes')->type('textarea')->hint('Explain any blocker if the task could not be achieved.');
        }
    }

    protected function setupUpdateOperation(): void
    {
        CRUD::setValidation(TaskRequest::class);

        if (backpack_user()?->isAdmin()) {
            $this->addAdminTaskFields(includeApprovalField: true);
        } else {
            $this->setupCreateOperation();
        }
    }

    protected function setupShowOperation(): void
    {
        $this->hideActivityButtonsWhenUnauthorized();

        CRUD::column('scheduled_for')->label('Date')->type('date');
        CRUD::addColumn([
            'name' => 'scheduled_time_label',
            'label' => 'Time',
            'type' => 'text',
            'value' => fn (Task $task): string => $task->scheduledTimeLabel(),
        ]);
        CRUD::addColumn([
            'name' => 'title',
            'label' => 'Title',
            'type' => 'text',
            'limit' => 10000,
            'wrapper' => [
                'style' => 'white-space: normal; word-break: break-word;',
            ],
        ]);
        CRUD::column('description')->label('Description')->type('textarea');
        CRUD::addColumn([
            'name' => 'status_label',
            'label' => 'Status',
            'type' => 'text',
            'value' => fn (Task $task): string => TaskStatus::options()[$task->status->value] ?? $task->status->value,
        ]);
        CRUD::column('approved_as_completed')
            ->label('Approved Completed')
            ->type('boolean');
        CRUD::column('sort_order')->label('Sort Order');
        CRUD::column('outcome_notes')->label('Outcome Notes')->type('textarea');
        CRUD::column('started_at')->label('Started At')->type('datetime');
        CRUD::column('completed_at')->label('Completed At')->type('datetime');
        CRUD::addColumn([
            'name' => 'assignee_id',
            'label' => 'Staff',
            'type' => 'select',
            'entity' => 'assignee',
            'attribute' => 'name',
            'model' => User::class,
        ]);
        CRUD::addColumn([
            'name' => 'admin_id',
            'label' => 'Created By',
            'type' => 'select',
            'entity' => 'admin',
            'attribute' => 'name',
            'model' => User::class,
        ]);
    }

    protected function setupModelActivityOperationDefaults(): void
    {
        if (! $this->canViewActivityButtons()) {
            CRUD::denyAccess('logsActivityOperation');

            return;
        }

        $this->traitSetupModelActivityOperationDefaults();
    }

    protected function setupEntryActivityOperationDefaults(): void
    {
        if (! $this->canViewActivityButtons()) {
            CRUD::denyAccess('logsActivityOperation');

            return;
        }

        $this->traitSetupEntryActivityOperationDefaults();
    }

    public function store()
    {
        $this->crud->hasAccessOrFail('create');
        $request = $this->crud->validateRequest();
        $this->crud->registerFieldEvents();

        $item = $this->crud->create(array_merge(
            $this->crud->getStrippedSaveRequest($request),
            ['admin_id' => backpack_user()->getKey()],
        ));
        $this->data['entry'] = $this->crud->entry = $item;

        $this->sendTaskNotifications->sendAssigned(collect([$item]));

        \Alert::success(trans('backpack::crud.insert_success'))->flash();
        $this->crud->setSaveAction();

        return $this->crud->performSaveAction($item->getKey());
    }

    public function bulkCreate(): View
    {
        abort_unless(backpack_user()?->isAdmin(), Response::HTTP_FORBIDDEN);

        return view('admin.tasks.bulk-create', [
            'staffMembers' => User::query()->staff()->orderBy('name')->get(),
        ]);
    }

    public function bulkStore(Request $request): RedirectResponse
    {
        abort_unless(backpack_user()?->isAdmin(), Response::HTTP_FORBIDDEN);

        $validated = $request->validate([
            'scheduled_for' => ['required', 'date'],
            'scheduled_time' => ['nullable', 'date_format:H:i'],
            'task_lines' => ['required', 'string'],
            'assignee_id' => [
                'required',
                'uuid',
                Rule::exists('users', 'id')->where('role', UserRole::Staff->value),
            ],
        ], [
            'task_lines.required' => 'Please enter at least one task title.',
        ], [
            'assignee_id' => 'staff member',
            'scheduled_for' => 'scheduled date',
            'scheduled_time' => 'scheduled time',
            'task_lines' => 'task list',
        ]);

        $taskTitles = collect(preg_split('/\r\n|\r|\n/', (string) $validated['task_lines']))
            ->map(fn (string $title): string => trim($title))
            ->filter()
            ->values();

        if ($taskTitles->isEmpty()) {
            return back()
                ->withErrors(['task_lines' => 'Please enter at least one task title.'])
                ->withInput();
        }

        $createdTasks = DB::transaction(function () use ($validated, $taskTitles): Collection {
            $startingSortOrder = (int) Task::query()
                ->staffTasks()
                ->where('assignee_id', $validated['assignee_id'])
                ->whereDate('scheduled_for', $validated['scheduled_for'])
                ->max('sort_order');

            $createdTasks = collect();

            foreach ($taskTitles as $index => $title) {
                $createdTasks->push(Task::query()->create([
                    'title' => $title,
                    'scheduled_for' => $validated['scheduled_for'],
                    'scheduled_time' => $validated['scheduled_time'] ?? '23:59',
                    'status' => TaskStatus::Pending,
                    'sort_order' => $startingSortOrder + $index + 1,
                    'admin_id' => backpack_user()->getKey(),
                    'assignee_id' => $validated['assignee_id'],
                ]));
            }

            return $createdTasks;
        });

        $this->sendTaskNotifications->sendAssigned($createdTasks);

        \Alert::success($taskTitles->count().' tasks were assigned successfully.')->flash();

        return redirect()->to(backpack_url('tasks'));
    }

    public function bulkUpdate(Request $request): View
    {
        abort_unless(backpack_user()?->isAdmin(), Response::HTTP_FORBIDDEN);

        $filters = $this->bulkTaskFilters($request);
        $tasks = $this->bulkTaskQuery($filters)
            ->with(['assignee'])
            ->orderBy('scheduled_for')
            ->orderBy('scheduled_time')
            ->orderBy('sort_order')
            ->get();

        return view('admin.tasks.bulk-update', [
            'staffMembers' => User::query()->staff()->orderBy('name')->get(),
            'tasks' => $tasks,
            'filters' => $filters,
            'taskStatusOptions' => TaskStatus::options(),
        ]);
    }

    public function bulkDelete(Request $request): View
    {
        abort_unless(backpack_user()?->isAdmin(), Response::HTTP_FORBIDDEN);

        $filters = $this->bulkTaskFilters($request);
        $tasks = $this->bulkTaskQuery($filters)
            ->with(['assignee'])
            ->orderBy('scheduled_for')
            ->orderBy('scheduled_time')
            ->orderBy('sort_order')
            ->get();

        return view('admin.tasks.bulk-delete', [
            'staffMembers' => User::query()->staff()->orderBy('name')->get(),
            'tasks' => $tasks,
            'filters' => $filters,
            'taskStatusOptions' => TaskStatus::options(),
        ]);
    }

    public function bulkDeleteDestroy(Request $request): RedirectResponse
    {
        abort_unless(backpack_user()?->isAdmin(), Response::HTTP_FORBIDDEN);

        $validated = $request->validate([
            'task_ids' => ['required', 'array', 'min:1'],
            'task_ids.*' => ['required', 'uuid', Rule::exists('tasks', 'id')],
            'filter_assignee_id' => ['nullable', 'uuid'],
            'filter_scheduled_for' => ['nullable', 'date'],
            'filter_status' => ['nullable', 'string'],
            'filter_approval_status' => ['nullable', 'string'],
        ], [], [
            'task_ids' => 'tasks',
        ]);

        $selectedTasks = Task::query()
            ->staffTasks()
            ->whereKey($validated['task_ids'])
            ->get();

        DB::transaction(function () use ($selectedTasks): void {
            $selectedTasks->each(fn (Task $task): bool => (bool) $task->delete());
        });

        \Alert::success($selectedTasks->count().' task(s) were deleted successfully.')->flash();

        return redirect()->route('tasks.bulk-delete', array_filter([
            'filter_assignee_id' => $validated['filter_assignee_id'] ?? null,
            'filter_scheduled_for' => $validated['filter_scheduled_for'] ?? null,
            'filter_status' => $validated['filter_status'] ?? null,
            'filter_approval_status' => $validated['filter_approval_status'] ?? null,
        ], fn ($value) => filled($value)));
    }

    public function bulkUpdateStore(Request $request): RedirectResponse
    {
        abort_unless(backpack_user()?->isAdmin(), Response::HTTP_FORBIDDEN);

        $validated = $request->validate([
            'task_ids' => ['required', 'array', 'min:1'],
            'task_ids.*' => ['required', 'uuid', Rule::exists('tasks', 'id')],
            'assignee_id' => [
                'nullable',
                'uuid',
                Rule::exists('users', 'id')->where('role', UserRole::Staff->value),
            ],
            'scheduled_for' => ['nullable', 'date'],
            'scheduled_time' => ['nullable', 'date_format:H:i'],
            'status' => ['nullable', Rule::enum(TaskStatus::class)],
            'approval_action' => ['nullable', Rule::in(['approve', 'unapprove'])],
            'filter_assignee_id' => ['nullable', 'uuid'],
            'filter_scheduled_for' => ['nullable', 'date'],
            'filter_status' => ['nullable', 'string'],
            'filter_approval_status' => ['nullable', 'string'],
        ], [], [
            'task_ids' => 'tasks',
            'assignee_id' => 'staff member',
            'scheduled_for' => 'scheduled date',
            'scheduled_time' => 'scheduled time',
            'approval_action' => 'approval status',
        ]);

        $hasChanges = filled($validated['assignee_id'] ?? null)
            || filled($validated['scheduled_for'] ?? null)
            || filled($validated['scheduled_time'] ?? null)
            || filled($validated['status'] ?? null)
            || filled($validated['approval_action'] ?? null);

        if (! $hasChanges) {
            return back()
                ->withErrors(['bulk_update' => 'Choose at least one field to update.'])
                ->withInput();
        }

        $selectedTasks = Task::query()
            ->staffTasks()
            ->whereKey($validated['task_ids'])
            ->get();

        $assignedTasks = collect();
        $approvedTasks = collect();

        DB::transaction(function () use ($validated, $selectedTasks, $assignedTasks, $approvedTasks): void {
            foreach ($selectedTasks as $task) {
                $wasAssigneeId = $task->assignee_id;
                $wasApprovedAsCompleted = $task->approved_as_completed;

                $updateData = [];

                if (filled($validated['assignee_id'] ?? null)) {
                    $updateData['assignee_id'] = $validated['assignee_id'];
                }

                if (filled($validated['scheduled_for'] ?? null)) {
                    $updateData['scheduled_for'] = $validated['scheduled_for'];
                }

                if (filled($validated['scheduled_time'] ?? null)) {
                    $updateData['scheduled_time'] = $validated['scheduled_time'];
                }

                if (filled($validated['status'] ?? null)) {
                    $updateData['status'] = $validated['status'];
                }

                if (filled($validated['approval_action'] ?? null)) {
                    $updateData['approved_as_completed'] = $validated['approval_action'] === 'approve';
                }

                $task->update($updateData);
                $task->refresh();

                if ($task->assignee_id !== $wasAssigneeId) {
                    $assignedTasks->push($task);
                }

                if (! $wasApprovedAsCompleted && $task->approved_as_completed && $task->status === TaskStatus::Completed) {
                    $approvedTasks->push($task);
                }
            }
        });

        $this->sendTaskNotifications->sendAssigned($assignedTasks);
        $this->sendTaskNotifications->sendApproved($approvedTasks);

        \Alert::success($selectedTasks->count().' task(s) were updated successfully.')->flash();

        return redirect()->route('tasks.bulk-update', array_filter([
            'filter_assignee_id' => $validated['filter_assignee_id'] ?? null,
            'filter_scheduled_for' => $validated['filter_scheduled_for'] ?? null,
            'filter_status' => $validated['filter_status'] ?? null,
            'filter_approval_status' => $validated['filter_approval_status'] ?? null,
        ], fn ($value) => filled($value)));
    }

    public function update()
    {
        $task = $this->findSharedTaskOrFail((string) request()->route('id'));
        $wasAssigneeId = $task->assignee_id;
        $wasApprovedAsCompleted = $task->approved_as_completed;

        $response = $this->traitUpdate();

        $task->refresh();

        if ($task->assignee_id !== $wasAssigneeId) {
            $this->sendTaskNotifications->sendAssigned(collect([$task]));
        }

        if (! $wasApprovedAsCompleted && $task->approved_as_completed && $task->status === TaskStatus::Completed) {
            $this->sendTaskNotifications->sendApproved(collect([$task]));
        }

        return $response;
    }

    public function edit($id)
    {
        $task = $this->findSharedTaskOrFail((string) $id);
        abort_unless(backpack_user()->can('update', $task), 403);

        return $this->traitEdit($id);
    }

    public function show($id)
    {
        $task = Task::query()
            ->staffTasks()
            ->with([
                'admin',
                'assignee',
                'remarks' => fn ($query) => $query
                    ->whereNull('parent_remark_id')
                    ->with([
                        'author',
                        'responses.author',
                        'responses.responses.author',
                    ])
                    ->orderBy('created_at'),
            ])
            ->findOrFail($id);
        abort_unless(backpack_user()->can('view', $task), 403);

        if (backpack_user()?->isStaff()) {
            Widget::add([
                'type' => 'view',
                'view' => 'admin.tasks.widgets.actions',
                'task' => $task,
            ])->to('before_content');
        }

        Widget::add([
            'type' => 'view',
            'view' => 'admin.tasks.widgets.remarks',
            'task' => $task,
        ])->to('after_content');

        return $this->traitShow($id);
    }

    public function storeRemark(Request $request, string $id): RedirectResponse
    {
        $task = $this->findSharedTaskOrFail($id);

        abort_unless(backpack_user()->can('view', $task), 403);
        abort_unless(backpack_user()->can('create', TaskRemark::class), 403);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'parent_remark_id' => ['nullable', 'uuid'],
        ]);

        $parentRemark = null;
        if (! empty($validated['parent_remark_id'])) {
            $parentRemark = TaskRemark::query()
                ->whereKey($validated['parent_remark_id'])
                ->where('task_id', $task->getKey())
                ->firstOrFail();
        }

        TaskRemark::query()->create([
            'task_id' => $task->getKey(),
            'author_id' => backpack_user()->getKey(),
            'parent_remark_id' => $parentRemark?->getKey(),
            'body' => $validated['body'],
            'is_admin_remark' => backpack_user()->isAdmin(),
        ]);

        \Alert::success($parentRemark ? 'Response added.' : 'Remark added.')->flash();

        return redirect()->to(backpack_url("tasks/{$task->getKey()}/show"));
    }

    public function markInProgress(string $id): RedirectResponse
    {
        $task = $this->findSharedTaskOrFail($id);

        abort_unless(backpack_user()->can('update', $task), Response::HTTP_FORBIDDEN);
        abort_unless(backpack_user()?->isStaff(), Response::HTTP_FORBIDDEN);

        if ($task->status === TaskStatus::InProgress) {
            \Alert::info('Task is already in progress.')->flash();

            return redirect()->to(backpack_url("tasks/{$task->getKey()}/show"));
        }

        if ($task->status !== TaskStatus::Pending) {
            \Alert::warning('This task can no longer be started from its current status.')->flash();

            return redirect()->to(backpack_url("tasks/{$task->getKey()}/show"));
        }

        $task->update([
            'status' => TaskStatus::InProgress,
        ]);

        \Alert::success('Task marked as in progress.')->flash();

        return redirect()->to(backpack_url("tasks/{$task->getKey()}/show"));
    }

    public function markCompleted(string $id): RedirectResponse
    {
        $task = $this->findSharedTaskOrFail($id);

        abort_unless(backpack_user()->can('update', $task), Response::HTTP_FORBIDDEN);
        abort_unless(backpack_user()?->isStaff(), Response::HTTP_FORBIDDEN);

        if ($task->status === TaskStatus::Completed) {
            \Alert::info('Task is already marked as completed.')->flash();

            return redirect()->to(backpack_url("tasks/{$task->getKey()}/show"));
        }

        if (! in_array($task->status, [TaskStatus::Pending, TaskStatus::InProgress], true)) {
            \Alert::warning('This task cannot be marked as completed from its current status.')->flash();

            return redirect()->to(backpack_url("tasks/{$task->getKey()}/show"));
        }

        $task->update([
            'status' => TaskStatus::Completed,
        ]);

        \Alert::success('Task marked as completed.')->flash();

        return redirect()->to(backpack_url("tasks/{$task->getKey()}/show"));
    }

    public function approveCompleted(string $id): RedirectResponse
    {
        $task = $this->findSharedTaskOrFail($id);

        abort_unless(backpack_user()?->isAdmin(), Response::HTTP_FORBIDDEN);
        abort_unless($task->status === TaskStatus::Completed, Response::HTTP_UNPROCESSABLE_ENTITY);

        $task->update([
            'approved_as_completed' => true,
        ]);

        $this->sendTaskNotifications->sendApproved(collect([$task]));

        \Alert::success('Task approved as completed.')->flash();

        return redirect()->back();
    }

    public function approveAllCompleted(): RedirectResponse
    {
        abort_unless(backpack_user()?->isAdmin(), Response::HTTP_FORBIDDEN);

        $approvedTasks = Task::query()
            ->staffTasks()
            ->with('assignee')
            ->where('status', TaskStatus::Completed->value)
            ->where('approved_as_completed', false)
            ->get();

        $approvedTasks->each(fn (Task $task) => $task->update([
            'approved_as_completed' => true,
        ]));

        $this->sendTaskNotifications->sendApproved($approvedTasks);
        $approvedCount = $approvedTasks->count();

        \Alert::success("{$approvedCount} task(s) approved as completed.")->flash();

        return redirect()->back();
    }

    private function denyAllAccess(): void
    {
        foreach (['list', 'show', 'create', 'update', 'delete'] as $operation) {
            CRUD::denyAccess($operation);
        }
    }

    private function canViewActivityButtons(): bool
    {
        return backpack_user()?->hasAdminEmailAccess() ?? false;
    }

    private function hideActivityButtonsWhenUnauthorized(): void
    {
        if ($this->canViewActivityButtons()) {
            return;
        }

        CRUD::removeButton('view_model_logs');
        CRUD::removeButton('view_entry_logs');
    }

    private function applyTaskFilters(): void
    {
        $startDate = $this->parseFilterDate(request()->query('start_date'));
        $endDate = $this->parseFilterDate(request()->query('end_date'));
        $staffId = request()->query('staff_id');
        $status = request()->query('status');
        $approvalStatus = request()->query('approval_status');

        if ($startDate !== null) {
            CRUD::addClause('whereDate', 'scheduled_for', '>=', $startDate->toDateString());
        }

        if ($endDate !== null) {
            CRUD::addClause('whereDate', 'scheduled_for', '<=', $endDate->toDateString());
        }

        if (backpack_user()?->isAdmin() && is_string($staffId) && $staffId !== '') {
            $staffExists = User::query()
                ->staff()
                ->whereKey($staffId)
                ->exists();

            if ($staffExists) {
                CRUD::addClause('where', 'assignee_id', $staffId);
            }
        }

        if (is_string($status) && array_key_exists($status, TaskStatus::options())) {
            CRUD::addClause('where', 'status', $status);
        }

        if (backpack_user()?->isAdmin() && is_string($approvalStatus) && $approvalStatus !== '') {
            if ($approvalStatus === 'pending') {
                CRUD::addClause('where', 'status', TaskStatus::Completed->value);
                CRUD::addClause('where', 'approved_as_completed', false);
            }

            if ($approvalStatus === 'approved') {
                CRUD::addClause('where', 'status', TaskStatus::Completed->value);
                CRUD::addClause('where', 'approved_as_completed', true);
            }
        }
    }

    private function parseFilterDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function addAdminTaskFields(bool $includeApprovalField = false): void
    {
        CRUD::field('title')->label('Title')->type('text');
        CRUD::field('description')->label('Description')->type('textarea');
        CRUD::field('scheduled_for')->label('Scheduled Date')->type('date')->default(today()->toDateString());
        CRUD::field('scheduled_time')->label('Scheduled Time')->type('time')->default('23:59')->attributes(['step' => 60]);
        CRUD::field('assignee_id')->label('Staff Member')->type('select')->entity('assignee')->model(User::class)->attribute('name')->options(
            fn ($query) => $query->staff()->orderBy('name')->get()
        );
        CRUD::field('status')->label('Status')->type('select_from_array')->options(TaskStatus::options())->default(TaskStatus::Pending->value);
        if ($includeApprovalField) {
            CRUD::field('approved_as_completed')
                ->label('Approved As Completed')
                ->type('checkbox')
                ->hint('Only approved completed tasks count as completed in dashboard and summary totals.');
        }
        CRUD::field('sort_order')->label('Sort Order')->type('number')->default(1)->attributes(['min' => 0]);
        CRUD::field('outcome_notes')->label('Outcome Notes')->type('textarea');
    }

    /**
     * @return array{filter_assignee_id: ?string, filter_scheduled_for: ?string, filter_status: ?string, filter_approval_status: ?string}
     */
    private function bulkTaskFilters(Request $request): array
    {
        return [
            'filter_assignee_id' => $request->string('filter_assignee_id')->toString() ?: null,
            'filter_scheduled_for' => $request->query('filter_scheduled_for', $request->query->count() > 0 ? null : today()->toDateString()),
            'filter_status' => $request->string('filter_status')->toString() ?: null,
            'filter_approval_status' => $request->string('filter_approval_status')->toString() ?: null,
        ];
    }

    /**
     * @param  array{filter_assignee_id: ?string, filter_scheduled_for: ?string, filter_status: ?string, filter_approval_status: ?string}  $filters
     */
    private function bulkTaskQuery(array $filters)
    {
        $query = Task::query()->staffTasks();

        if (filled($filters['filter_assignee_id'])) {
            $query->where('assignee_id', $filters['filter_assignee_id']);
        }

        if (filled($filters['filter_scheduled_for'])) {
            $query->whereDate('scheduled_for', $filters['filter_scheduled_for']);
        }

        if (filled($filters['filter_status']) && array_key_exists($filters['filter_status'], TaskStatus::options())) {
            $query->where('status', $filters['filter_status']);
        }

        if ($filters['filter_approval_status'] === 'pending') {
            $query->where('status', TaskStatus::Completed->value)
                ->where('approved_as_completed', false);
        }

        if ($filters['filter_approval_status'] === 'approved') {
            $query->where('status', TaskStatus::Completed->value)
                ->where('approved_as_completed', true);
        }

        return $query;
    }

    private function findSharedTaskOrFail(string $id): Task
    {
        return Task::query()->staffTasks()->findOrFail($id);
    }
}
