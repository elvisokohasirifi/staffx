<?php

namespace App\Http\Controllers\Admin;

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
    }

    public function setup(): void
    {
        CRUD::setModel(Task::class);
        CRUD::setRoute(trim((string) config('backpack.base.route_prefix'), '/').'/tasks');
        CRUD::setEntityNameStrings('task', 'tasks');
        CRUD::with(['admin', 'assignee']);

        $this->crud->query->withCount('remarks')->orderBy('scheduled_for')->orderBy('sort_order');

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
            'name' => 'status_label',
            'label' => 'Status',
            'type' => 'text',
            'value' => fn (Task $task): string => TaskStatus::options()[$task->status->value] ?? $task->status->value,
        ]);
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
            $this->addAdminTaskFields();
        } else {
            $this->setupCreateOperation();
        }
    }

    protected function setupShowOperation(): void
    {
        $this->hideActivityButtonsWhenUnauthorized();

        CRUD::column('scheduled_for')->label('Date')->type('date');
        CRUD::column('title')->label('Title');
        CRUD::column('description')->label('Description')->type('textarea');
        CRUD::addColumn([
            'name' => 'status_label',
            'label' => 'Status',
            'type' => 'text',
            'value' => fn (Task $task): string => TaskStatus::options()[$task->status->value] ?? $task->status->value,
        ]);
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

        DB::transaction(function () use ($validated, $taskTitles): void {
            $startingSortOrder = (int) Task::query()
                ->where('assignee_id', $validated['assignee_id'])
                ->whereDate('scheduled_for', $validated['scheduled_for'])
                ->max('sort_order');

            foreach ($taskTitles as $index => $title) {
                Task::query()->create([
                    'title' => $title,
                    'scheduled_for' => $validated['scheduled_for'],
                    'status' => TaskStatus::Pending,
                    'sort_order' => $startingSortOrder + $index + 1,
                    'admin_id' => backpack_user()->getKey(),
                    'assignee_id' => $validated['assignee_id'],
                ]);
            }
        });

        \Alert::success($taskTitles->count().' tasks were assigned successfully.')->flash();

        return redirect()->to(backpack_url('tasks'));
    }

    public function edit($id)
    {
        $task = Task::query()->findOrFail($id);
        abort_unless(backpack_user()->can('update', $task), 403);

        return $this->traitEdit($id);
    }

    public function show($id)
    {
        $task = Task::query()
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
        $task = Task::query()->findOrFail($id);

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
        $task = Task::query()->findOrFail($id);

        abort_unless(backpack_user()->can('update', $task), Response::HTTP_FORBIDDEN);
        abort_unless(backpack_user()?->isStaff(), Response::HTTP_FORBIDDEN);
        abort_unless($task->status === TaskStatus::Pending, Response::HTTP_UNPROCESSABLE_ENTITY);

        $task->update([
            'status' => TaskStatus::InProgress,
        ]);

        \Alert::success('Task marked as in progress.')->flash();

        return redirect()->to(backpack_url("tasks/{$task->getKey()}/show"));
    }

    public function markCompleted(string $id): RedirectResponse
    {
        $task = Task::query()->findOrFail($id);

        abort_unless(backpack_user()->can('update', $task), Response::HTTP_FORBIDDEN);
        abort_unless(backpack_user()?->isStaff(), Response::HTTP_FORBIDDEN);
        abort_unless($task->status === TaskStatus::InProgress, Response::HTTP_UNPROCESSABLE_ENTITY);

        $task->update([
            'status' => TaskStatus::Completed,
        ]);

        \Alert::success('Task marked as completed.')->flash();

        return redirect()->to(backpack_url("tasks/{$task->getKey()}/show"));
    }

    private function denyAllAccess(): void
    {
        foreach (['list', 'show', 'create', 'update', 'delete'] as $operation) {
            CRUD::denyAccess($operation);
        }
    }

    private function canViewActivityButtons(): bool
    {
        return backpack_user()?->email === 'elvisokohasirifi@gmail.com';
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

    private function addAdminTaskFields(): void
    {
        CRUD::field('title')->label('Title')->type('text');
        CRUD::field('description')->label('Description')->type('textarea');
        CRUD::field('scheduled_for')->label('Scheduled Date')->type('date')->default(today()->toDateString());
        CRUD::field('assignee_id')->label('Staff Member')->type('select')->entity('assignee')->model(User::class)->attribute('name')->options(
            fn ($query) => $query->staff()->orderBy('name')->get()
        );
        CRUD::field('status')->label('Status')->type('select_from_array')->options(TaskStatus::options())->default(TaskStatus::Pending->value);
        CRUD::field('sort_order')->label('Sort Order')->type('number')->default(1)->attributes(['min' => 0]);
        CRUD::field('outcome_notes')->label('Outcome Notes')->type('textarea');
    }
}
