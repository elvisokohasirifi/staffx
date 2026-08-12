<?php

namespace App\Http\Requests;

use App\TaskStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminPersonalTaskRequest extends FormRequest
{
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
            'scheduled_for' => ['required', 'date'],
            'status' => ['required', Rule::enum(TaskStatus::class)],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'outcome_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
