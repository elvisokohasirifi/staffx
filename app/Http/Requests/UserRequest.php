<?php

namespace App\Http\Requests;

use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = backpack_user();
        $staff = $this->route('id') ? User::query()->find($this->route('id')) : null;

        if (! $user) {
            return false;
        }

        if ($staff) {
            return $user->can('update', $staff);
        }

        return $user->can('create', User::class);
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        $userId = $this->route('id');
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId),
            ],
        ];

        if (backpack_user()?->canManageAllUsers() && $this->route('id')) {
            $rules['password'] = ['nullable', 'string', 'min:8'];
            $rules['role'] = ['required', Rule::enum(UserRole::class)];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'email' => 'email address',
            'role' => 'role',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'That email address is already in use.',
        ];
    }
}
