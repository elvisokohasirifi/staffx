<?php

namespace App\Http\Requests;

use App\Models\Department;
use App\Rules\AdminInCurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DepartmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return config('app.is_tenant') && (backpack_user()?->isOrganizationOwner() ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique((new Department)->getTable(), 'name')
                    ->where('organization_id', backpack_user()?->organization_id)
                    ->ignore($this->route('id')),
            ],
            'administrators' => ['nullable', 'array'],
            'administrators.*' => ['required', 'uuid', new AdminInCurrentOrganization],
        ];
    }
}
