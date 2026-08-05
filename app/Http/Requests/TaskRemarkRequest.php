<?php

namespace App\Http\Requests;

use App\Models\Task;
use App\Models\TaskRemark;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TaskRemarkRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = backpack_user();
        $remark = $this->route('id') ? TaskRemark::query()->find($this->route('id')) : null;

        if (! $user) {
            return false;
        }

        if ($remark) {
            return $user->can('update', $remark);
        }

        if (! $user->can('create', TaskRemark::class)) {
            return false;
        }

        $task = $this->filled('task_id') ? Task::query()->find($this->input('task_id')) : null;

        return $task ? $user->can('view', $task) : true;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'task_id' => ['required', 'uuid', Rule::exists('tasks', 'id')],
            'parent_remark_id' => [
                'nullable',
                'uuid',
                Rule::exists('task_remarks', 'id'),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! $value || ! $this->filled('task_id')) {
                        return;
                    }

                    $parentTaskId = TaskRemark::query()
                        ->whereKey($value)
                        ->value('task_id');

                    if ($parentTaskId !== $this->input('task_id')) {
                        $fail('Replies must belong to the same task.');
                    }
                },
            ],
            'body' => ['required', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'task_id' => 'task',
            'parent_remark_id' => 'parent remark',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'body.required' => 'Please enter a remark or response.',
        ];
    }
}
