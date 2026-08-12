<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\AdminPersonalTaskRequest;
use App\Models\Task;
use App\TaskStatus;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanel;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Database\Eloquent\Builder;
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
class AdminPersonalTaskCrudController extends CrudController
{
    use CreateOperation;
    use DeleteOperation;
    use ListOperation;
    use ShowOperation {
        show as traitShow;
    }
    use UpdateOperation {
        edit as traitEdit;
        update as traitUpdate;
    }

    public function setup(): void
    {
        abort_unless(backpack_user()?->isAdmin(), 403);

        CRUD::setModel(Task::class);
        CRUD::setRoute(trim((string) config('backpack.base.route_prefix'), '/').'/my-tasks');
        CRUD::setEntityNameStrings('my task', 'my tasks');

        CRUD::addClause('where', 'is_admin_personal', true);
        CRUD::addClause('where', 'admin_id', backpack_user()->getKey());
        CRUD::addClause('where', 'assignee_id', backpack_user()->getKey());
        CRUD::setAccessCondition('show', fn (Task $task): bool => $this->isOwnedPersonalTask($task));
        CRUD::setAccessCondition('update', fn (Task $task): bool => $this->isOwnedPersonalTask($task));
        CRUD::setAccessCondition('delete', fn (Task $task): bool => $this->isOwnedPersonalTask($task));

        foreach (['list', 'show', 'create', 'update', 'delete'] as $operation) {
            CRUD::allowAccess($operation);
        }
        CRUD::with(['admin']);
        $this->crud->query->orderBy('scheduled_for')->orderBy('sort_order')->orderBy('title');
    }

    protected function setupListOperation(): void
    {
        $this->applyTaskFilters();
        CRUD::setListView('admin.personal-tasks.list');
        CRUD::setDefaultPageLength(20);
        CRUD::setPageLengthMenu([20, 50, 100]);
        CRUD::addButtonFromView('top', 'bulk_create_my_tasks', 'vendor.backpack.crud.buttons.bulk_create_my_tasks', 'end');
        CRUD::addButtonFromView('top', 'bulk_delete_my_tasks', 'vendor.backpack.crud.buttons.bulk_delete_my_tasks', 'end');
        CRUD::addButtonFromView('top', 'bulk_update_my_tasks', 'vendor.backpack.crud.buttons.bulk_update_my_tasks', 'end');

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
        CRUD::column('outcome_notes')->label('Notes')->type('textarea');
    }

    protected function setupCreateOperation(): void
    {
        CRUD::setValidation(AdminPersonalTaskRequest::class);
        $this->addFields();
    }

    protected function setupUpdateOperation(): void
    {
        CRUD::setValidation(AdminPersonalTaskRequest::class);
        $this->addFields();
    }

    protected function setupShowOperation(): void
    {
        CRUD::column('scheduled_for')->label('Date')->type('date');
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
        CRUD::column('outcome_notes')->label('Outcome Notes')->type('textarea');
        CRUD::column('created_at')->label('Created At')->type('datetime');
        CRUD::column('updated_at')->label('Updated At')->type('datetime');
    }

    public function store()
    {
        $this->crud->hasAccessOrFail('create');
        $request = $this->crud->validateRequest();
        $this->crud->registerFieldEvents();

        $item = $this->crud->create(array_merge(
            $this->crud->getStrippedSaveRequest($request),
            [
                'admin_id' => backpack_user()->getKey(),
                'assignee_id' => backpack_user()->getKey(),
                'is_admin_personal' => true,
                'approved_as_completed' => false,
            ],
        ));

        $this->data['entry'] = $this->crud->entry = $item;

        \Alert::success(trans('backpack::crud.insert_success'))->flash();
        $this->crud->setSaveAction();

        return $this->crud->performSaveAction($item->getKey());
    }

    public function bulkCreate(): View
    {
        abort_unless(backpack_user()?->isAdmin(), Response::HTTP_FORBIDDEN);

        return view('admin.personal-tasks.bulk-create');
    }

    public function bulkStore(Request $request): RedirectResponse
    {
        abort_unless(backpack_user()?->isAdmin(), Response::HTTP_FORBIDDEN);

        $validated = $request->validate([
            'scheduled_for' => ['required', 'date'],
            'task_lines' => ['required', 'string'],
        ], [
            'task_lines.required' => 'Please enter at least one task title.',
        ], [
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

        DB::transaction(function () use ($taskTitles, $validated): void {
            $startingSortOrder = (int) Task::query()
                ->adminPersonalTasks()
                ->where('admin_id', backpack_user()->getKey())
                ->where('assignee_id', backpack_user()->getKey())
                ->whereDate('scheduled_for', $validated['scheduled_for'])
                ->max('sort_order');

            $taskTitles->each(function (string $title, int $index) use ($startingSortOrder, $validated): void {
                Task::query()->create([
                    'title' => $title,
                    'scheduled_for' => $validated['scheduled_for'],
                    'status' => TaskStatus::Pending,
                    'sort_order' => $startingSortOrder + $index + 1,
                    'admin_id' => backpack_user()->getKey(),
                    'assignee_id' => backpack_user()->getKey(),
                    'is_admin_personal' => true,
                    'approved_as_completed' => false,
                ]);
            });
        });

        \Alert::success($taskTitles->count().' personal task(s) were created successfully.')->flash();

        return redirect()->to(backpack_url('my-tasks'));
    }

    public function bulkUpdate(Request $request): View
    {
        abort_unless(backpack_user()?->isAdmin(), Response::HTTP_FORBIDDEN);

        $filters = $this->bulkTaskFilters($request);
        $tasks = $this->bulkTaskQuery($filters)
            ->orderBy('scheduled_for')
            ->orderBy('sort_order')
            ->get();

        return view('admin.personal-tasks.bulk-update', [
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
            ->orderBy('scheduled_for')
            ->orderBy('sort_order')
            ->get();

        return view('admin.personal-tasks.bulk-delete', [
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
            'task_ids.*' => ['required', 'uuid'],
            'filter_scheduled_for' => ['nullable', 'date'],
            'filter_status' => ['nullable', 'string'],
            'filter_approval_status' => ['nullable', 'string'],
        ], [], [
            'task_ids' => 'tasks',
        ]);

        $selectedTasks = Task::query()
            ->adminPersonalTasks()
            ->where('admin_id', backpack_user()->getKey())
            ->where('assignee_id', backpack_user()->getKey())
            ->whereKey($validated['task_ids'])
            ->get();

        DB::transaction(function () use ($selectedTasks): void {
            $selectedTasks->each(fn (Task $task): bool => (bool) $task->delete());
        });

        \Alert::success($selectedTasks->count().' personal task(s) were deleted successfully.')->flash();

        return redirect()->route('my-tasks.bulk-delete', array_filter([
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
            'task_ids.*' => ['required', 'uuid'],
            'scheduled_for' => ['nullable', 'date'],
            'status' => ['nullable', Rule::enum(TaskStatus::class)],
            'approval_action' => ['nullable', Rule::in(['approve', 'unapprove'])],
            'filter_scheduled_for' => ['nullable', 'date'],
            'filter_status' => ['nullable', 'string'],
            'filter_approval_status' => ['nullable', 'string'],
        ], [], [
            'task_ids' => 'tasks',
            'scheduled_for' => 'scheduled date',
            'approval_action' => 'approval status',
        ]);

        $hasChanges = filled($validated['scheduled_for'] ?? null)
            || filled($validated['status'] ?? null)
            || filled($validated['approval_action'] ?? null);

        if (! $hasChanges) {
            return back()
                ->withErrors(['bulk_update' => 'Choose at least one field to update.'])
                ->withInput();
        }

        $selectedTasks = Task::query()
            ->adminPersonalTasks()
            ->where('admin_id', backpack_user()->getKey())
            ->where('assignee_id', backpack_user()->getKey())
            ->whereKey($validated['task_ids'])
            ->get();

        DB::transaction(function () use ($validated, $selectedTasks): void {
            foreach ($selectedTasks as $task) {
                $updateData = [];

                if (filled($validated['scheduled_for'] ?? null)) {
                    $updateData['scheduled_for'] = $validated['scheduled_for'];
                }

                if (filled($validated['status'] ?? null)) {
                    $updateData['status'] = $validated['status'];
                }

                if (filled($validated['approval_action'] ?? null)) {
                    $updateData['approved_as_completed'] = $validated['approval_action'] === 'approve';
                }

                $task->update($updateData);
            }
        });

        \Alert::success($selectedTasks->count().' personal task(s) were updated successfully.')->flash();

        return redirect()->route('my-tasks.bulk-update', array_filter([
            'filter_scheduled_for' => $validated['filter_scheduled_for'] ?? null,
            'filter_status' => $validated['filter_status'] ?? null,
            'filter_approval_status' => $validated['filter_approval_status'] ?? null,
        ], fn ($value) => filled($value)));
    }

    public function update()
    {
        $task = $this->findPersonalTaskOrFail((string) request()->route('id'));

        request()->merge([
            'admin_id' => $task->admin_id,
            'assignee_id' => $task->assignee_id,
            'is_admin_personal' => true,
            'approved_as_completed' => false,
        ]);

        return $this->traitUpdate();
    }

    public function show($id)
    {
        $this->findPersonalTaskOrFail((string) $id);

        return $this->traitShow($id);
    }

    public function edit($id)
    {
        $this->findPersonalTaskOrFail((string) $id);

        return $this->traitEdit($id);
    }

    private function addFields(): void
    {
        CRUD::field('title')->label('Title')->type('text');
        CRUD::field('description')->label('Description')->type('textarea');
        CRUD::field('scheduled_for')->label('Scheduled Date')->type('date')->default(today()->toDateString());
        CRUD::field('status')->label('Status')->type('select_from_array')->options(TaskStatus::options())->default(TaskStatus::Pending->value);
        CRUD::field('sort_order')->label('Sort Order')->type('number')->default(1)->attributes(['min' => 0]);
        CRUD::field('outcome_notes')->label('Outcome Notes')->type('textarea');
    }

    /**
     * @return array{filter_scheduled_for: ?string, filter_status: ?string, filter_approval_status: ?string}
     */
    private function bulkTaskFilters(Request $request): array
    {
        return [
            'filter_scheduled_for' => $request->query('filter_scheduled_for', $request->query->count() > 0 ? null : today()->toDateString()),
            'filter_status' => $request->string('filter_status')->toString() ?: null,
            'filter_approval_status' => $request->string('filter_approval_status')->toString() ?: null,
        ];
    }

    /**
     * @param  array{filter_scheduled_for: ?string, filter_status: ?string, filter_approval_status: ?string}  $filters
     */
    private function bulkTaskQuery(array $filters): Builder
    {
        $query = Task::query()
            ->adminPersonalTasks()
            ->where('admin_id', backpack_user()->getKey())
            ->where('assignee_id', backpack_user()->getKey());

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

    private function applyTaskFilters(): void
    {
        $startDate = $this->parseFilterDate(request()->query('start_date'));
        $endDate = $this->parseFilterDate(request()->query('end_date'));
        $status = request()->query('status');

        if ($startDate !== null) {
            CRUD::addClause('whereDate', 'scheduled_for', '>=', $startDate->toDateString());
        }

        if ($endDate !== null) {
            CRUD::addClause('whereDate', 'scheduled_for', '<=', $endDate->toDateString());
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

    private function findPersonalTaskOrFail(string $id): Task
    {
        return Task::query()
            ->adminPersonalTasks()
            ->where('admin_id', backpack_user()->getKey())
            ->where('assignee_id', backpack_user()->getKey())
            ->findOrFail($id);
    }

    private function isOwnedPersonalTask(Task $task): bool
    {
        return $task->is_admin_personal
            && $task->admin_id === backpack_user()?->getKey()
            && $task->assignee_id === backpack_user()?->getKey();
    }
}
