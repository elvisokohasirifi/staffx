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
