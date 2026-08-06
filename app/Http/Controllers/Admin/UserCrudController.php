<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\UserRequest;
use App\Models\User;
use App\UserRole;
use Backpack\ActivityLog\Http\Controllers\Operations\EntryActivityOperation;
use Backpack\ActivityLog\Http\Controllers\Operations\ModelActivityOperation;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use Backpack\CRUD\app\Library\Auth\PasswordBrokerManager;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanel;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;
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
    }

    public function setup(): void
    {
        CRUD::setModel(User::class);
        CRUD::setRoute(trim((string) config('backpack.base.route_prefix'), '/').'/staff');
        CRUD::setEntityNameStrings('staff member', 'staff');

        CRUD::addClause('where', 'role', UserRole::Staff->value);

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

        CRUD::column('name')->label('Name');
        CRUD::column('email')->label('Email');
        CRUD::column('email_verified_at')->label('Verified At')->type('datetime');
        CRUD::column('created_at')->label('Added On')->type('datetime');

        if ($this->canImpersonateUsers()) {
            CRUD::button('impersonate_user')
                ->stack('line')
                ->view('vendor.backpack.crud.buttons.impersonate_user');
        }
    }

    protected function setupCreateOperation(): void
    {
        CRUD::setValidation(UserRequest::class);

        CRUD::field('name')->label('Name')->type('text');
        CRUD::field('email')->label('Email')->type('email');
    }

    protected function setupUpdateOperation(): void
    {
        $this->setupCreateOperation();
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

    public function store()
    {
        $this->crud->hasAccessOrFail('create');
        $request = $this->crud->validateRequest();
        $this->crud->registerFieldEvents();

        $item = $this->crud->create(array_merge(
            $this->crud->getStrippedSaveRequest($request),
            [
                'role' => UserRole::Staff->value,
                'password' => Str::password(32),
            ],
        ));
        $this->data['entry'] = $this->crud->entry = $item;

        \Alert::success(trans('backpack::crud.insert_success'))->flash();
        $this->crud->setSaveAction();

        $status = $this->passwordBroker()->sendResetLink(['email' => $item->email]);

        if ($status === Password::RESET_LINK_SENT) {
            \Alert::info('A password reset link was emailed to the new staff member.')->flash();
        } else {
            \Alert::warning(trans($status))->flash();
        }

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

    private function canImpersonateUsers(): bool
    {
        return backpack_user()?->canImpersonateUsers() ?? false;
    }

    private function hideActivityButtonsWhenUnauthorized(): void
    {
        if ($this->canViewActivityButtons()) {
            return;
        }

        CRUD::removeButton('view_model_logs');
        CRUD::removeButton('view_entry_logs');
    }

    private function passwordBroker()
    {
        $manager = new PasswordBrokerManager(app());

        return $manager->broker(config('backpack.base.passwords'));
    }
}
