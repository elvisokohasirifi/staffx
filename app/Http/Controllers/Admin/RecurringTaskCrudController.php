<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Tasks\GenerateRecurringTasksAction;
use App\Actions\Tasks\SendTaskNotificationsAction;
use App\Http\Requests\RecurringTaskRequest;
use App\Models\RecurringTask;
use App\Models\User;
use App\RecurringTaskPattern;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanel;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

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
