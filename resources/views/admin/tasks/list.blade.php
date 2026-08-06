@extends(backpack_view('blank'))

@php
    $defaultBreadcrumbs = [
        trans('backpack::crud.admin') => url(config('backpack.base.route_prefix'), 'dashboard'),
        $crud->entity_name_plural => url($crud->route),
        trans('backpack::crud.list') => false,
    ];

    $breadcrumbs = $breadcrumbs ?? $defaultBreadcrumbs;
@endphp

@section('content')
    <div class="row" bp-section="crud-operation-list">
        <div class="{{ $crud->getListContentClass() }}">
            @include('admin.tasks.partials.date-range-filter')

            <x-backpack::datatable :controller="$controller" :crud="$crud" :modifiesUrl="true" />
        </div>
    </div>
@endsection

@push('after_scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const clearButton = document.getElementById('clearTaskFiltersButton');

            if (!clearButton) {
                return;
            }

            clearButton.addEventListener('click', function (event) {
                event.preventDefault();

                const table = document.querySelector('table[id^="crudTable"]');
                const persistentTableSlug = table?.getAttribute('data-persistent-table-slug');
                const tableId = table?.id;

                if (persistentTableSlug) {
                    localStorage.removeItem(`${persistentTableSlug}_list_url`);
                    localStorage.removeItem(`${persistentTableSlug}_list_url_time`);
                }

                if (tableId) {
                    Object.keys(localStorage)
                        .filter((key) => key.startsWith(`DataTables_${tableId}`))
                        .forEach((key) => localStorage.removeItem(key));
                }

                window.location.href = clearButton.href;
            });
        });
    </script>
@endpush
