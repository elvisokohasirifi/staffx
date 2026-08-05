<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\TaskRemarkRequest;
use App\Models\Task;
use App\Models\TaskRemark;
use App\Models\User;
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
class TaskRemarkCrudController extends CrudController
{
    use CreateOperation;
    use DeleteOperation;
    use ListOperation;
    use ShowOperation {
        show as traitShow;
    }
    use UpdateOperation;

    public function setup(): void
    {
        CRUD::setModel(TaskRemark::class);
        CRUD::setRoute(trim((string) config('backpack.base.route_prefix'), '/').'/remarks');
        CRUD::setEntityNameStrings('remark', 'remarks');
        CRUD::with(['task', 'author', 'parentRemark']);

        $this->crud->query->orderByDesc('created_at');

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
            CRUD::allowAccess('create');
            CRUD::addClause('whereHas', 'task', function ($query): void {
                $query->where('assignee_id', backpack_user()->getKey());
            });
            CRUD::setAccessCondition('show', fn (TaskRemark $remark): bool => backpack_user()->can('view', $remark));
        }
    }

    protected function setupListOperation(): void
    {
        CRUD::addColumn([
            'name' => 'task_id',
            'label' => 'Task',
            'type' => 'select',
            'entity' => 'task',
            'attribute' => 'title',
            'model' => Task::class,
        ]);
        CRUD::addColumn([
            'name' => 'author_id',
            'label' => 'Author',
            'type' => 'select',
            'entity' => 'author',
            'attribute' => 'name',
            'model' => User::class,
        ]);
        CRUD::addColumn([
            'name' => 'parent_remark_id',
            'label' => 'Reply To',
            'type' => 'select',
            'entity' => 'parentRemark',
            'attribute' => 'remark_preview',
            'model' => TaskRemark::class,
        ]);
        CRUD::column('is_admin_remark')->label('Admin Remark')->type('boolean');
        CRUD::column('body')->label('Message')->type('textarea');
        CRUD::column('created_at')->label('Created At')->type('datetime');
    }

    protected function setupCreateOperation(): void
    {
        CRUD::setValidation(TaskRemarkRequest::class);

        CRUD::field('task_id')->label('Task')->type('select')->entity('task')->model(Task::class)->attribute('title')->options(
            fn ($query) => backpack_user()->isAdmin()
                ? $query->orderBy('scheduled_for')->get()
                : $query->where('assignee_id', backpack_user()->getKey())->orderBy('scheduled_for')->get()
        );
        CRUD::field('parent_remark_id')->label('Reply To')->type('select')->entity('parentRemark')->model(TaskRemark::class)->attribute('remark_preview')->allows_null(true)->options(
            fn ($query) => backpack_user()->isAdmin()
                ? $query->latest()->get()
                : $query->whereHas('task', fn ($taskQuery) => $taskQuery->where('assignee_id', backpack_user()->getKey()))
                    ->latest()
                    ->get()
        );
        CRUD::field('body')->label('Message')->type('textarea');
    }

    protected function setupUpdateOperation(): void
    {
        $this->setupCreateOperation();
    }

    protected function setupShowOperation(): void
    {
        $this->setupListOperation();
    }

    public function store()
    {
        $this->crud->hasAccessOrFail('create');
        $request = $this->crud->validateRequest();
        $this->crud->registerFieldEvents();

        $item = $this->crud->create(array_merge(
            $this->crud->getStrippedSaveRequest($request),
            [
                'author_id' => backpack_user()->getKey(),
                'is_admin_remark' => backpack_user()->isAdmin(),
            ],
        ));
        $this->data['entry'] = $this->crud->entry = $item;

        \Alert::success(trans('backpack::crud.insert_success'))->flash();
        $this->crud->setSaveAction();

        return $this->crud->performSaveAction($item->getKey());
    }

    public function show($id)
    {
        $remark = TaskRemark::query()->findOrFail($id);
        abort_unless(backpack_user()->can('view', $remark), 403);

        return $this->traitShow($id);
    }

    private function denyAllAccess(): void
    {
        foreach (['list', 'show', 'create', 'update', 'delete'] as $operation) {
            CRUD::denyAccess($operation);
        }
    }
}
