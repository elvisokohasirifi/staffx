<?php

namespace App\Http\Requests;

use App\Rules\DepartmentInCurrentOrganization;
use App\Rules\StaffInCurrentOrganization;
use App\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkAssignStaffDepartmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return config('app.is_tenant') && (backpack_user()?->isAdmin() ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'staff_ids' => ['required', 'array', 'min:1'],
            'staff_ids.*' => [
                'required',
                'uuid',
                Rule::exists('users', 'id')->where('role', UserRole::Staff->value),
                new StaffInCurrentOrganization,
            ],
            'department_id' => ['required', 'uuid', new DepartmentInCurrentOrganization],
        ];
    }
}
