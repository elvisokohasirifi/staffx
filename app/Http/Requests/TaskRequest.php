<?php

namespace App\Http\Requests;

use App\Models\Task;
use App\TaskStatus;
use App\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = backpack_user();
        $task = $this->route('id') ? Task::query()->find($this->route('id')) : null;

        if (! $user) {
            return false;
        }

        if ($task) {
            return $user->can('update', $task);
        }

        return $user->can('create', Task::class);
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        if (backpack_user()?->isStaff()) {
            return [
                'status' => ['required', Rule::in([
                    TaskStatus::InProgress->value,
                    TaskStatus::Completed->value,
                    TaskStatus::CouldNotBeAchieved->value,
                ])],
                'outcome_notes' => [
                    'nullable',
                    'string',
                    'max:5000',
                    Rule::requiredIf($this->input('status') === TaskStatus::CouldNotBeAchieved->value),
                ],
            ];
        }

        if (! $this->route('id')) {
            return [
                'title' => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string'],
                'scheduled_for' => ['required', 'date'],
                'status' => ['required', Rule::enum(TaskStatus::class)],
                'sort_order' => ['nullable', 'integer', 'min:0'],
                'outcome_notes' => ['nullable', 'string', 'max:5000'],
                'assignee_id' => [
                    'required',
                    'uuid',
                    Rule::exists('users', 'id')->where('role', UserRole::Staff->value),
                ],
            ];
        }

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'scheduled_for' => ['required', 'date'],
            'status' => ['required', Rule::enum(TaskStatus::class)],
            'approved_as_completed' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'outcome_notes' => ['nullable', 'string', 'max:5000'],
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
            'scheduled_for' => 'scheduled date',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'outcome_notes.required' => 'Please explain why this task could not be achieved.',
        ];
    }
}
