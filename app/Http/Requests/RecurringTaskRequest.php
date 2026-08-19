<?php

namespace App\Http\Requests;

use App\Models\RecurringTask;
use App\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecurringTaskRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return backpack_user()?->isAdmin() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'scheduled_time' => ['nullable', 'date_format:H:i'],
            'recurring_days' => ['required', 'array', 'min:1'],
            'recurring_days.*' => ['required', 'string', Rule::in(array_keys(RecurringTask::recurringDayOptions()))],
            'is_active' => ['sometimes', 'boolean'],
            'assignee_id' => [
                'required',
                'uuid',
                Rule::exists('users', 'id')->where('role', UserRole::Staff->value),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'assignee_id' => 'staff member',
            'scheduled_time' => 'scheduled time',
            'recurring_days' => 'recurring days',
            'recurring_days.*' => 'recurring day',
        ];
    }
}
