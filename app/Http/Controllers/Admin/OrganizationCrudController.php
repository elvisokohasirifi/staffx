<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\OrganizationUpdateRequest;
use App\Models\Organization;
use App\Models\User;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

class OrganizationCrudController extends CrudController
{
    use ListOperation;
    use ShowOperation;
    use UpdateOperation {
        edit as traitEdit;
        update as traitUpdate;
    }

    public function setup(): void
    {
        abort_unless(config('app.is_tenant') && (backpack_user()?->hasAdminEmailAccess() ?? false), 403);

        CRUD::setModel(Organization::class);
        CRUD::setRoute(trim((string) config('backpack.base.route_prefix'), '/').'/organizations');
        CRUD::setEntityNameStrings('organization', 'organizations');
        CRUD::denyAccess(['create', 'delete']);
        CRUD::allowAccess(['list', 'show', 'update']);
        CRUD::setAccessCondition('update', fn (Organization $organization): bool => $organization->is_default);
        CRUD::with('owner');
    }

    protected function setupListOperation(): void
    {
        CRUD::column('name')->label('Organization')->type('text');
        CRUD::column('owner_id')
            ->label('Owner')
            ->type('select')
            ->entity('owner')
            ->model(User::class)
            ->attribute('name');
        CRUD::column('is_default')->label('Default')->type('boolean');
        CRUD::column('created_at')->label('Created At')->type('datetime');
    }

    protected function setupUpdateOperation(): void
    {
        CRUD::setValidation(OrganizationUpdateRequest::class);
        CRUD::field('name')->label('Organization Name')->type('text');
    }

    public function edit($id)
    {
        $this->ensureDefaultOrganization($id);

        return $this->traitEdit($id);
    }

    public function update()
    {
        $this->ensureDefaultOrganization(request()->route('id'));

        return $this->traitUpdate();
    }

    private function ensureDefaultOrganization(string $organizationId): void
    {
        abort_unless(
            Organization::query()
                ->whereKey($organizationId)
                ->where('is_default', true)
                ->exists(),
            403
        );
    }
}
