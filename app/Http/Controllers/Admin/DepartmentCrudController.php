<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\DepartmentRequest;
use App\Models\Department;
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
 * Class DepartmentCrudController
 *
 * @property-read CrudPanel $crud
 */
class DepartmentCrudController extends CrudController
{
    use CreateOperation;
    use DeleteOperation;
    use ListOperation;
    use ShowOperation;
    use UpdateOperation;

    /**
     * Configure the CrudPanel object. Apply settings to all operations.
     */
    public function setup(): void
    {
        abort_unless(config('app.is_tenant') && (backpack_user()?->isOrganizationOwner() ?? false), 403);

        CRUD::setModel(Department::class);
        CRUD::setRoute(trim((string) config('backpack.base.route_prefix'), '/').'/departments');
        CRUD::setEntityNameStrings('department', 'departments');
    }

    /**
     * Define what happens when the List operation is loaded.
     *
     * @see  https://backpackforlaravel.com/docs/crud-operation-list-entries
     */
    protected function setupListOperation(): void
    {
        CRUD::with('administrators');

        CRUD::column('name')->label('Name');
        CRUD::column('administrators')
            ->label('Department Admins')
            ->type('select_multiple')
            ->entity('administrators')
            ->model(User::class)
            ->attribute('name');
    }

    /**
     * Define what happens when the Create operation is loaded.
     *
     * @see https://backpackforlaravel.com/docs/crud-operation-create
     */
    protected function setupCreateOperation(): void
    {
        CRUD::setValidation(DepartmentRequest::class);

        CRUD::field('name')->label('Name')->type('text');
        CRUD::field('administrators')
            ->label('Department Admins')
            ->type('select_multiple')
            ->entity('administrators')
            ->model(User::class)
            ->attribute('name')
            ->pivot(true)
            ->options(fn ($query) => $query->admins()->orderBy('name')->get());
    }

    /**
     * Define what happens when the Update operation is loaded.
     *
     * @see https://backpackforlaravel.com/docs/crud-operation-update
     */
    protected function setupUpdateOperation(): void
    {
        $this->setupCreateOperation();
    }
}
