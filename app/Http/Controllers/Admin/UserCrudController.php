<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\BulkAssignStaffDepartmentRequest;
use App\Http\Requests\UserRequest;
use App\Models\Department;
use App\Models\User;
use App\Notifications\StaffAccountInvitationNotification;
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
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * @property-read CrudPanel $crud
 */
class UserCrudController extends CrudController
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

    public function setup(): void
    {
        CRUD::setModel(User::class);
        CRUD::setRoute(trim((string) config('backpack.base.route_prefix'), '/').'/staff');
        CRUD::setEntityNameStrings(
            $this->canManageAllUsers() ? 'user' : ($this->canManageOrganizationUsers() ? 'team member' : 'staff member'),
            $this->canManageAllUsers() ? 'users' : ($this->canManageOrganizationUsers() ? 'team' : 'staff')
        );

        if (! $this->canManageOrganizationUsers()) {
            CRUD::addClause('where', 'role', UserRole::Staff->value);
        }

        $this->denyAllAccess();

        if (backpack_user()?->isAdmin()) {
            CRUD::allowAccess('list');
            CRUD::allowAccess('show');
            CRUD::allowAccess('create');
            CRUD::allowAccess('update');
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

        if ($this->canBulkAssignDepartments()) {
            CRUD::allowAccess('bulk_assign_department');
            CRUD::button('bulk_assign_department')
                ->stack('top')
                ->view('crud::buttons.quick')
                ->meta([
                    'access' => true,
                    'label' => 'Bulk Assign Department',
                    'icon' => 'la la-sitemap',
                    'wrapper' => [
                        'element' => 'a',
                        'href' => route('staff.bulk-assign-department'),
                    ],
                ]);
        }

        CRUD::column('name')->label('Name');
        CRUD::column('email')->label('Email');
        if (config('app.is_tenant')) {
            CRUD::with('department');
            CRUD::column('department_id')
                ->label('Department')
                ->type('select')
                ->entity('department')
                ->model(Department::class)
                ->attribute('name');
        }
        if ($this->canManageOrganizationUsers()) {
            CRUD::addColumn([
                'name' => 'role',
                'label' => 'Role',
                'type' => 'text',
                'value' => fn (User $user): string => $user->role->value,
            ]);
        }
        CRUD::column('email_verified_at')->label('Verified At')->type('datetime');
        CRUD::column('created_at')->label('Added On')->type('datetime');

        if ($this->canImpersonateUsers()) {
            CRUD::button('impersonate_user')
                ->stack('line')
                ->view('vendor.backpack.crud.buttons.impersonate_user');
        }

        CRUD::button('view_tasks')
            ->stack('line')
            ->view('vendor.backpack.crud.buttons.view_tasks');
    }

    protected function setupCreateOperation(): void
    {
        CRUD::setValidation(UserRequest::class);

        CRUD::field('name')->label('Name')->type('text');
        CRUD::field('email')->label('Email')->type('email');
        $this->addDepartmentField();

        if ($this->canManageOrganizationUsers()) {
            $this->addRoleField();
        }
    }

    protected function setupUpdateOperation(): void
    {
        CRUD::setValidation(UserRequest::class);

        CRUD::field('name')->label('Name')->type('text');
        CRUD::field('email')->label('Email')->type('email');
        $this->addDepartmentField();

        if ($this->canManageOrganizationUsers()) {
            CRUD::field('password')
                ->label('Password')
                ->type('password')
                ->hint('Leave blank to keep the current password.');
            $this->addRoleField();
        }
    }

    protected function setupShowOperation(): void
    {
        $this->hideActivityButtonsWhenUnauthorized();
        $this->setupListOperation();
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

    public function store(): RedirectResponse
    {
        $this->crud->hasAccessOrFail('create');
        $request = $this->crud->validateRequest();
        $this->crud->registerFieldEvents();

        $item = $this->crud->create(array_merge(
            $this->crud->getStrippedSaveRequest($request),
            [
                'role' => $this->canManageOrganizationUsers()
                    ? $request->string('role')->value() ?: UserRole::Staff->value
                    : UserRole::Staff->value,
                'password' => Str::password(32),
            ],
        ));
        $this->data['entry'] = $this->crud->entry = $item;

        \Alert::success(trans('backpack::crud.insert_success'))->flash();
        $this->crud->setSaveAction();

        $item->notify(new StaffAccountInvitationNotification);
        \Alert::info('A StaffX invitation email was sent to the new staff member.')->flash();

        return $this->crud->performSaveAction($item->getKey());
    }

    public function edit($id)
    {
        $staff = User::query()->findOrFail($id);
        abort_unless(backpack_user()->can('update', $staff), 403);

        return $this->traitEdit($id);
    }

    public function show($id)
    {
        $staff = User::query()->findOrFail($id);
        abort_unless(backpack_user()->can('view', $staff), 403);

        return $this->traitShow($id);
    }

    public function update()
    {
        if ($this->canManageOrganizationUsers() && blank(request('password'))) {
            request()->request->remove('password');
        }

        return $this->traitUpdate();
    }

    public function impersonate(string $id): RedirectResponse
    {
        abort_unless($this->canImpersonateUsers(), 403);

        $userToImpersonate = User::query()->findOrFail($id);
        abort_if($userToImpersonate->is(backpack_user()), 422, 'You are already using this account.');
        $impersonatorId = session('impersonator_id', backpack_user()->getKey());

        backpack_auth()->login($userToImpersonate);
        session()->put('impersonator_id', $impersonatorId);

        \Alert::info('You are now impersonating '.$userToImpersonate->name.'.')->flash();

        return redirect()->to(backpack_url('dashboard'));
    }

    public function stopImpersonating(): RedirectResponse
    {
        $impersonatorId = session()->pull('impersonator_id');
        abort_unless(is_string($impersonatorId) && $impersonatorId !== '', 403);

        $impersonator = User::query()->findOrFail($impersonatorId);
        abort_unless($impersonator->canImpersonateUsers(), 403);

        backpack_auth()->login($impersonator);

        \Alert::success('You have returned to your account.')->flash();

        return redirect()->to(backpack_url('dashboard'));
    }

    public function bulkAssignDepartment(): View
    {
        abort_unless($this->canBulkAssignDepartments(), 403);

        return view('admin.staff.bulk-assign-department', [
            'departments' => Department::query()->orderBy('name')->get(['id', 'name']),
            'staffMembers' => User::query()
                ->staff()
                ->with('department')
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'department_id']),
        ]);
    }

    public function bulkAssignDepartmentStore(BulkAssignStaffDepartmentRequest $request): RedirectResponse
    {
        abort_unless($this->canBulkAssignDepartments(), 403);

        $validated = $request->validated();
        $staffMembers = User::query()
            ->staff()
            ->whereKey($validated['staff_ids'])
            ->get();

        DB::transaction(function () use ($staffMembers, $validated): void {
            $staffMembers->each(fn (User $staffMember) => $staffMember->update([
                'department_id' => $validated['department_id'],
            ]));
        });

        \Alert::success($staffMembers->count().' staff member(s) assigned to the department.')->flash();

        return redirect()->route('staff.bulk-assign-department');
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

    private function canImpersonateUsers(): bool
    {
        return backpack_user()?->canImpersonateUsers() ?? false;
    }

    private function canManageAllUsers(): bool
    {
        return backpack_user()?->canManageAllUsers() ?? false;
    }

    private function canManageOrganizationUsers(): bool
    {
        return backpack_user()?->canManageOrganizationUsers() ?? false;
    }

    private function canBulkAssignDepartments(): bool
    {
        return config('app.is_tenant') && (backpack_user()?->isAdmin() ?? false);
    }

    private function addRoleField(): void
    {
        CRUD::field('role')
            ->label('Role')
            ->type('select_from_array')
            ->options([
                UserRole::Admin->value => 'Admin',
                UserRole::Staff->value => 'Staff',
            ])
            ->default(UserRole::Staff->value);
    }

    private function addDepartmentField(): void
    {
        if (! config('app.is_tenant')) {
            return;
        }

        CRUD::field('department_id')
            ->label('Department')
            ->type('select')
            ->entity('department')
            ->model(Department::class)
            ->attribute('name')
            ->allows_null(true)
            ->options(fn ($query) => $query->orderBy('name')->get());
    }

    private function hideActivityButtonsWhenUnauthorized(): void
    {
        if ($this->canViewActivityButtons()) {
            return;
        }

        CRUD::removeButton('view_model_logs');
        CRUD::removeButton('view_entry_logs');
    }
}
