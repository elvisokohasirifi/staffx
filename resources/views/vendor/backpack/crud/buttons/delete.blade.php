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

@pushOnce('after_scripts', 'backpack-delete-entry-button')
<script>
    if (typeof deleteEntry !== 'function') {
        $("[data-button-type=delete]").unbind('click');

        function deleteEntry(button) {
            var route = $(button).attr('data-route');

            swal({
                title: button.getAttribute('data-warning-text'),
                text: button.getAttribute('data-confirm-text'),
                icon: "warning",
                buttons: {
                    cancel: {
                        text: button.getAttribute('data-cancel-text'),
                        value: null,
                        visible: true,
                        className: "bg-secondary",
                        closeModal: true,
                    },
                    delete: {
                        text: button.getAttribute('data-delete-text'),
                        value: true,
                        visible: true,
                        className: "bg-danger",
                    },
                },
                dangerMode: true,
            }).then((value) => {
                function showDeleteNotyAlert() {
                    new Noty({
                        type: "success",
                        text: button.getAttribute('data-delete-confirmation-text')
                    }).show();
                }

                if (value) {
                    $.ajax({
                        url: route,
                        type: 'DELETE',
                        success: function(result) {
                            if (result == 1) {
                                let tableId = $(button).data('table-id') || 'crudTable';

                                if (typeof window.crud !== 'undefined' &&
                                    typeof window.crud.tables !== 'undefined' &&
                                    window.crud.tables[tableId]) {
                                    let table = window.crud.tables[tableId];

                                    if (table.rows().count() === 1) {
                                        table.page("previous");
                                    }

                                    $('.dtr-modal-close').click();

                                    showDeleteNotyAlert();
                                    table.draw(false);
                                } else {
                                    let redirectRoute = $(button).data('redirect-route');

                                    if (redirectRoute) {
                                        localStorage.setItem('backpack_alerts', JSON.stringify({
                                            success: [
                                                button.getAttribute('data-delete-confirmation-title')
                                            ]
                                        }));
                                        window.location.href = redirectRoute;
                                    } else {
                                        showDeleteNotyAlert();
                                    }
                                }
                            } else if (result instanceof Object) {
                                Object.entries(result).forEach(function(entry) {
                                    var type = entry[0];
                                    entry[1].forEach(function(message) {
                                        new Noty({
                                            type: type,
                                            text: message
                                        }).show();
                                    });
                                });
                            } else {
                                swal({
                                    title: button.getAttribute('data-error-title'),
                                    text: button.getAttribute('data-error-text'),
                                    icon: "error",
                                    timer: 4000,
                                    buttons: false,
                                });
                            }
                        },
                        error: function() {
                            swal({
                                title: button.getAttribute('data-error-title'),
                                text: button.getAttribute('data-error-text'),
                                icon: "error",
                                timer: 4000,
                                buttons: false,
                            });
                        }
                    });
                }
            });
        }
    }
</script>
@endPushOnce
