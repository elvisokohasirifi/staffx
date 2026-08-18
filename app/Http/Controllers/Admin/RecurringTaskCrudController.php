<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Tasks\GenerateRecurringTasksAction;
use App\Actions\Tasks\SendTaskNotificationsAction;
use App\Http\Requests\RecurringTaskRequest;
use App\Models\RecurringTask;
use App\Models\User;
use App\RecurringTaskPattern;
use App\UserRole;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanel;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * @property-read CrudPanel $crud
 */
class RecurringTaskCrudController extends CrudController
{
    use CreateOperation;
    use DeleteOperation;
    use ListOperation;
    use ShowOperation;
    use UpdateOperation {
        update as traitUpdate;
    }

    public function __construct(
        private GenerateRecurringTasksAction $generateRecurringTasks,
        private SendTaskNotificationsAction $sendTaskNotifications,
    ) {
        parent::__construct();
    }

    public function setup(): void
    {
        abort_unless(backpack_user()?->isAdmin(), 403);

        CRUD::setModel(RecurringTask::class);
        CRUD::setRoute(trim((string) config('backpack.base.route_prefix'), '/').'/recurring-tasks');
        CRUD::setEntityNameStrings('recurring task', 'recurring tasks');
        CRUD::with(['admin', 'assignee']);
        $this->crud->query->orderBy('title');

        foreach (['list', 'show', 'create', 'update', 'delete'] as $operation) {
            CRUD::allowAccess($operation);
        }
    }

    protected function setupListOperation(): void
    {
        $this->applyFilters();
        CRUD::setListView('admin.recurring-tasks.list');
        CRUD::setDefaultPageLength(20);
        CRUD::setPageLengthMenu([20, 50, 100]);
        CRUD::addButtonFromView('top', 'bulk_create_recurring_tasks', 'vendor.backpack.crud.buttons.bulk_create_recurring_tasks', 'end');

        CRUD::addColumn([
            'name' => 'title',
            'label' => 'Task',
            'type' => 'text',
            'limit' => 10000,
            'wrapper' => [
                'style' => 'white-space: normal; word-break: break-word; min-width: 260px;',
            ],
        ]);
        CRUD::addColumn([
            'name' => 'assignee_id',
            'label' => 'Staff',
            'type' => 'select',
            'entity' => 'assignee',
            'attribute' => 'name',
            'model' => User::class,
        ]);
        CRUD::addColumn([
            'name' => 'scheduled_time_label',
            'label' => 'Time',
            'type' => 'text',
            'value' => fn (RecurringTask $recurringTask): string => $recurringTask->scheduledTimeLabel(),
        ]);
        CRUD::addColumn([
            'name' => 'repeat_pattern_label',
            'label' => 'Repeat Pattern',
            'type' => 'text',
            'value' => fn (RecurringTask $recurringTask): string => RecurringTaskPattern::options()[$recurringTask->repeat_pattern->value] ?? $recurringTask->repeat_pattern->value,
        ]);
        CRUD::column('is_active')->label('Active')->type('boolean');
        CRUD::addColumn([
            'name' => 'admin_id',
            'label' => 'Created By',
            'type' => 'select',
            'entity' => 'admin',
            'attribute' => 'name',
            'model' => User::class,
        ]);
    }

    protected function setupCreateOperation(): void
    {
        CRUD::setValidation(RecurringTaskRequest::class);
        $this->addFields();
    }

    protected function setupUpdateOperation(): void
    {
        CRUD::setValidation(RecurringTaskRequest::class);
        $this->addFields();
    }

    protected function setupShowOperation(): void
    {
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
            'name' => 'assignee_id',
            'label' => 'Staff',
            'type' => 'select',
            'entity' => 'assignee',
            'attribute' => 'name',
            'model' => User::class,
        ]);
        CRUD::addColumn([
            'name' => 'scheduled_time_label',
            'label' => 'Time',
            'type' => 'text',
            'value' => fn (RecurringTask $recurringTask): string => $recurringTask->scheduledTimeLabel(),
        ]);
        CRUD::addColumn([
            'name' => 'repeat_pattern_label',
            'label' => 'Repeat Pattern',
            'type' => 'text',
            'value' => fn (RecurringTask $recurringTask): string => RecurringTaskPattern::options()[$recurringTask->repeat_pattern->value] ?? $recurringTask->repeat_pattern->value,
        ]);
        CRUD::column('is_active')->label('Active')->type('boolean');
        CRUD::addColumn([
            'name' => 'admin_id',
            'label' => 'Created By',
            'type' => 'select',
            'entity' => 'admin',
            'attribute' => 'name',
            'model' => User::class,
        ]);
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
            ['admin_id' => backpack_user()->getKey()],
        ));
        $this->data['entry'] = $this->crud->entry = $item;

        $createdTasks = $this->generateRecurringTasks->execute(today(), collect([$item]));
        $this->sendTaskNotifications->sendAssigned($createdTasks);

        \Alert::success(trans('backpack::crud.insert_success'))->flash();
        $this->crud->setSaveAction();

        return $this->crud->performSaveAction($item->getKey());
    }

    public function update()
    {
        $recurringTask = RecurringTask::query()->findOrFail((string) request()->route('id'));

        request()->merge([
            'admin_id' => $recurringTask->admin_id,
        ]);

        return $this->traitUpdate();
    }

    public function bulkCreate(): View
    {
        abort_unless(backpack_user()?->isAdmin(), Response::HTTP_FORBIDDEN);

        return view('admin.recurring-tasks.bulk-create', [
            'staffMembers' => User::query()->staff()->orderBy('name')->get(),
            'repeatPatternOptions' => RecurringTaskPattern::options(),
        ]);
    }

    public function bulkStore(Request $request): RedirectResponse
    {
        abort_unless(backpack_user()?->isAdmin(), Response::HTTP_FORBIDDEN);

        $validated = $request->validate([
            'scheduled_time' => ['nullable', 'date_format:H:i'],
            'task_lines' => ['required', 'string'],
            'repeat_pattern' => ['required', Rule::enum(RecurringTaskPattern::class)],
            'is_active' => ['nullable', 'boolean'],
            'assignee_id' => [
                'required',
                'uuid',
                Rule::exists('users', 'id')->where('role', UserRole::Staff->value),
            ],
        ], [
            'task_lines.required' => 'Please enter at least one recurring task title.',
        ], [
            'assignee_id' => 'staff member',
            'scheduled_time' => 'scheduled time',
            'repeat_pattern' => 'repeat pattern',
            'task_lines' => 'task list',
        ]);

        $taskTitles = collect(preg_split('/\r\n|\r|\n/', (string) $validated['task_lines']))
            ->map(fn (string $title): string => trim($title))
            ->filter()
            ->values();

        if ($taskTitles->isEmpty()) {
            return back()
                ->withErrors(['task_lines' => 'Please enter at least one recurring task title.'])
                ->withInput();
        }

        /** @var Collection<int, RecurringTask> $createdRecurringTasks */
        $createdRecurringTasks = DB::transaction(function () use ($taskTitles, $validated): Collection {
            $createdRecurringTasks = collect();

            foreach ($taskTitles as $title) {
                $createdRecurringTasks->push(RecurringTask::query()->create([
                    'title' => $title,
                    'scheduled_time' => $validated['scheduled_time'] ?? '23:59',
                    'repeat_pattern' => $validated['repeat_pattern'],
                    'is_active' => filter_var($validated['is_active'] ?? true, FILTER_VALIDATE_BOOL),
                    'admin_id' => backpack_user()->getKey(),
                    'assignee_id' => $validated['assignee_id'],
                ]));
            }

            return $createdRecurringTasks;
        });

        $createdTasks = $this->generateRecurringTasks->execute(today(), $createdRecurringTasks);
        $this->sendTaskNotifications->sendAssigned($createdTasks);

        \Alert::success($createdRecurringTasks->count().' recurring task(s) were created successfully.')->flash();

        return redirect()->to(backpack_url('recurring-tasks'));
    }

    private function addFields(): void
    {
        CRUD::field('title')->label('Title')->type('text');
        CRUD::field('description')->label('Description')->type('textarea');
        CRUD::field('assignee_id')->label('Staff Member')->type('select')->entity('assignee')->model(User::class)->attribute('name')->options(
            fn ($query) => $query->staff()->orderBy('name')->get()
        );
        CRUD::field('scheduled_time')->label('Scheduled Time')->type('time')->default('23:59')->attributes(['step' => 60]);
        CRUD::field('repeat_pattern')->label('Repeat Pattern')->type('select_from_array')->options(RecurringTaskPattern::options())->default(RecurringTaskPattern::Weekdays->value);
        CRUD::field('is_active')->label('Active')->type('checkbox')->default(true);
    }

    private function applyFilters(): void
    {
        $staffId = request()->query('staff_id');
        $repeatPattern = request()->query('repeat_pattern');
        $active = request()->query('active');

        if (is_string($staffId) && $staffId !== '') {
            $staffExists = User::query()
                ->staff()
                ->whereKey($staffId)
                ->exists();

            if ($staffExists) {
                CRUD::addClause('where', 'assignee_id', $staffId);
            }
        }

        if (is_string($repeatPattern) && array_key_exists($repeatPattern, RecurringTaskPattern::options())) {
            CRUD::addClause('where', 'repeat_pattern', $repeatPattern);
        }

        if ($active === '1') {
            CRUD::addClause('where', 'is_active', true);
        }

        if ($active === '0') {
            CRUD::addClause('where', 'is_active', false);
        }
    }
}
