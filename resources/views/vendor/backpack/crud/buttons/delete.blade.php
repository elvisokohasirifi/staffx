@php
    $redirectUrl = $crud->getOperationSetting('deleteButtonRedirect');
    if ($redirectUrl && $redirectUrl instanceof \Closure) {
        $redirectUrl = $redirectUrl();
    }
    $redirectUrl = filter_var($redirectUrl, FILTER_VALIDATE_URL) ? $redirectUrl : null;
@endphp

@if ($crud->hasAccess('delete', $entry))
    <a href="javascript:void(0)"
        onclick="deleteEntry(this)"
        bp-button="delete"
        data-redirect-route="{{ $redirectUrl }}"
        data-route="{{ url($crud->route.'/'.$entry->getKey()) }}"
        data-table-id="{{ isset($crudTableId) ? $crudTableId : 'crudTable' }}"
        data-warning-text="{!! trans('backpack::base.warning') !!}"
        data-confirm-text="{!! trans('backpack::crud.delete_confirm') !!}"
        data-cancel-text="{!! trans('backpack::crud.cancel') !!}"
        data-delete-text="{!! trans('backpack::crud.delete') !!}"
        data-error-title="{!! trans('backpack::crud.delete_confirmation_not_title') !!}"
        data-error-text="{!! trans('backpack::crud.delete_confirmation_not_message') !!}"
        data-delete-confirmation-text="{!! '<strong>'.trans('backpack::crud.delete_confirmation_title').'</strong><br>'.trans('backpack::crud.delete_confirmation_message') !!}"
        class="btn btn-sm btn-link"
        data-button-type="delete"
    >
        <i class="la la-trash"></i> <span>{{ trans('backpack::crud.delete') }}</span>
    </a>
@endif

@include('vendor.backpack.crud.buttons.inc.delete_entry_script')
