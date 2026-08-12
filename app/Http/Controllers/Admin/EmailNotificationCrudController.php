<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Notifications\SendAdminEmailNotificationAction;
use App\Http\Requests\EmailNotificationRequest;
use App\Models\EmailNotification;
use App\Models\User;
use App\UserRole;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanel;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Support\Facades\DB;

/**
 * @property-read CrudPanel $crud
 */
class EmailNotificationCrudController extends CrudController
{
    use CreateOperation;
    use ListOperation;
    use ShowOperation {
        show as traitShow;
    }

    public function __construct(private SendAdminEmailNotificationAction $sendAdminEmailNotification)
    {
        parent::__construct();
    }

    public function setup(): void
    {
        CRUD::setModel(EmailNotification::class);
        CRUD::setRoute(trim((string) config('backpack.base.route_prefix'), '/').'/email-notifications');
        CRUD::setEntityNameStrings('email notification', 'email notifications');
        $this->denyAllAccess();

        if (backpack_user()?->isAdmin()) {
            CRUD::allowAccess('list');
            CRUD::allowAccess('show');
            CRUD::allowAccess('create');
        }

        CRUD::with('sentBy');
        $this->crud->query->latest('sent_at')->latest('created_at');
    }

    protected function setupListOperation(): void
    {
        CRUD::addColumn([
            'name' => 'subject',
            'label' => 'Subject',
            'type' => 'text',
            'limit' => 10000,
            'wrapper' => [
                'style' => 'white-space: normal; word-break: break-word; min-width: 260px;',
            ],
        ]);
        CRUD::addColumn([
            'name' => 'recipient_roles_summary',
            'label' => 'Roles',
            'type' => 'text',
            'value' => fn (EmailNotification $emailNotification): string => $emailNotification->recipientRoleLabels() ?: '—',
        ]);
        CRUD::column('recipient_count')->label('Recipients')->type('number');
        CRUD::addColumn([
            'name' => 'sent_by_id',
            'label' => 'Sent By',
            'type' => 'select',
            'entity' => 'sentBy',
            'attribute' => 'name',
            'model' => User::class,
        ]);
        CRUD::column('sent_at')->label('Sent At')->type('datetime');
    }

    protected function setupCreateOperation(): void
    {
        CRUD::setValidation(EmailNotificationRequest::class);

        CRUD::field('subject')->label('Subject')->type('text');
        CRUD::field('body')
            ->label('Body')
            ->type('textarea')
            ->attributes(['rows' => 8]);
        CRUD::field([
            'name' => 'recipient_roles',
            'label' => 'Recipient Roles',
            'type' => 'select_from_array',
            'options' => UserRole::options(),
            'allows_multiple' => true,
            'default' => [UserRole::Staff->value],
            'hint' => 'Choose roles to email, or leave this empty and pick specific recipients instead.',
        ])->wrapper(['class' => 'form-group col-md-6']);
        CRUD::field([
            'name' => 'recipient_roles_clear',
            'type' => 'custom_html',
            'value' => <<<'HTML'
<button
    type="button"
    class="btn btn-sm btn-outline-secondary mb-3"
    onclick="document.querySelectorAll('[name=&quot;recipient_roles[]&quot;] option').forEach((option) => option.selected = false); document.querySelector('[name=&quot;recipient_roles[]&quot;]')?.dispatchEvent(new Event('change', { bubbles: true }));"
>
    Clear role selection
</button>
HTML,
        ])->wrapper(['class' => 'form-group col-md-6']);
        CRUD::field([
            'name' => 'recipient_user_ids',
            'label' => 'Specific Recipients',
            'type' => 'select_from_array',
            'options' => User::query()
                ->orderBy('name')
                ->get()
                ->mapWithKeys(fn (User $user): array => [$user->getKey() => "{$user->name} ({$user->email})"])
                ->all(),
            'allows_multiple' => true,
            'hint' => 'Choose specific recipients, or leave this empty and target roles instead.',
        ])->wrapper(['class' => 'form-group col-md-6']);
        CRUD::field([
            'name' => 'recipient_users_clear',
            'type' => 'custom_html',
            'value' => <<<'HTML'
<button
    type="button"
    class="btn btn-sm btn-outline-secondary mb-3"
    onclick="document.querySelectorAll('[name=&quot;recipient_user_ids[]&quot;] option').forEach((option) => option.selected = false); document.querySelector('[name=&quot;recipient_user_ids[]&quot;]')?.dispatchEvent(new Event('change', { bubbles: true }));"
>
    Clear specific recipients
</button>
HTML,
        ])->wrapper(['class' => 'form-group col-md-6']);
    }

    protected function setupShowOperation(): void
    {
        CRUD::column('subject')->label('Subject');
        CRUD::column('body')->label('Body')->type('textarea');
        CRUD::addColumn([
            'name' => 'recipient_roles_summary',
            'label' => 'Recipient Roles',
            'type' => 'text',
            'value' => fn (EmailNotification $emailNotification): string => $emailNotification->recipientRoleLabels() ?: '—',
        ]);
        CRUD::addColumn([
            'name' => 'recipient_user_labels',
            'label' => 'Specific Recipients',
            'type' => 'text',
            'value' => fn (EmailNotification $emailNotification): string => $emailNotification->recipientUserLabels() ?: '—',
        ]);
        CRUD::column('recipient_count')->label('Recipients')->type('number');
        CRUD::addColumn([
            'name' => 'sent_by_id',
            'label' => 'Sent By',
            'type' => 'select',
            'entity' => 'sentBy',
            'attribute' => 'name',
            'model' => User::class,
        ]);
        CRUD::column('sent_at')->label('Sent At')->type('datetime');
        CRUD::column('created_at')->label('Created At')->type('datetime');
    }

    public function store()
    {
        $this->crud->hasAccessOrFail('create');
        $request = $this->crud->validateRequest();
        $this->crud->registerFieldEvents();

        $emailNotification = null;

        DB::transaction(function () use ($request, &$emailNotification): void {
            $emailNotification = $this->crud->create(array_merge(
                $this->crud->getStrippedSaveRequest($request),
                [
                    'sent_by_id' => backpack_user()->getKey(),
                    'sent_at' => now(),
                    'recipient_count' => 0,
                ],
            ));

            $recipientCount = $this->sendAdminEmailNotification->send($emailNotification);

            $emailNotification->update([
                'recipient_count' => $recipientCount,
            ]);
        });

        $this->data['entry'] = $this->crud->entry = $emailNotification;

        \Alert::success('Email notification queued successfully.')->flash();
        $this->crud->setSaveAction();

        return $this->crud->performSaveAction($emailNotification->getKey());
    }

    public function show($id)
    {
        return $this->traitShow($id);
    }

    private function denyAllAccess(): void
    {
        foreach (['list', 'show', 'create', 'update', 'delete'] as $operation) {
            CRUD::denyAccess($operation);
        }
    }
}
