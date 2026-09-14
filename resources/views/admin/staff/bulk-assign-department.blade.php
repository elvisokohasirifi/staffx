@extends(backpack_view('blank'))

@section('content')
    <div class="mb-4">
        <h2 class="mb-1">Bulk Assign Department</h2>
        <p class="text-muted mb-0">Select the staff members to move, then choose their department.</p>
    </div>

    <form method="POST" action="{{ route('staff.bulk-assign-department-store') }}">
        @csrf

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">Staff Members</h4>
                        <span class="text-muted small">{{ $staffMembers->count() }} available</span>
                    </div>
                    <div class="card-body p-0">
                        @if ($staffMembers->isEmpty())
                            <div class="p-4 text-muted">There are no staff members to assign.</div>
                        @else
                            <div class="table-responsive">
                                <table class="table mb-0">
                                    <thead>
                                        <tr>
                                            <th style="width: 48px;">
                                                <input type="checkbox" id="select_all_staff">
                                            </th>
                                            <th>Staff Member</th>
                                            <th>Current Department</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($staffMembers as $staffMember)
                                            <tr>
                                                <td>
                                                    <input
                                                        type="checkbox"
                                                        name="staff_ids[]"
                                                        value="{{ $staffMember->getKey() }}"
                                                        class="bulk-staff-checkbox"
                                                        @checked(in_array($staffMember->getKey(), old('staff_ids', []), true))
                                                    >
                                                </td>
                                                <td>
                                                    <div class="fw-semibold">{{ $staffMember->name }}</div>
                                                    <div class="text-muted small">{{ $staffMember->email }}</div>
                                                </td>
                                                <td>{{ $staffMember->department?->name ?? 'Unassigned' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                        @error('staff_ids')
                            <div class="text-danger small p-3">{{ $message }}</div>
                        @enderror
                        @error('staff_ids.*')
                            <div class="text-danger small p-3 pt-0">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">Department</h4>
                    </div>
                    <div class="card-body">
                        <div class="mb-4">
                            <label for="department_id" class="form-label">Assign To</label>
                            <select id="department_id" name="department_id" class="form-select @error('department_id') is-invalid @enderror" required>
                                <option value="">Choose a department</option>
                                @foreach ($departments as $department)
                                    <option value="{{ $department->getKey() }}" @selected(old('department_id') === $department->getKey())>{{ $department->name }}</option>
                                @endforeach
                            </select>
                            @error('department_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary" @disabled($staffMembers->isEmpty() || $departments->isEmpty())>Assign Selected Staff</button>
                            <a href="{{ backpack_url('staff') }}" class="btn btn-link">Cancel</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection

@push('after_scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const selectAll = document.getElementById('select_all_staff');
            const checkboxes = document.querySelectorAll('.bulk-staff-checkbox');

            if (!selectAll || checkboxes.length === 0) {
                return;
            }

            selectAll.addEventListener('change', function () {
                checkboxes.forEach(function (checkbox) {
                    checkbox.checked = selectAll.checked;
                });
            });
        });
    </script>
@endpush
