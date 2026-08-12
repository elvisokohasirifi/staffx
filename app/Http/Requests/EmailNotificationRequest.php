<?php

namespace App\Http\Requests;

use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class EmailNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
            'recipient_user_ids' => ['nullable', 'array'],
            'recipient_user_ids.*' => ['uuid', Rule::exists('users', 'id')],
            'recipient_roles' => ['nullable', 'array'],
            'recipient_roles.*' => [Rule::enum(UserRole::class)],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $userIds = collect($this->input('recipient_user_ids', []))
                    ->filter()
                    ->values();
                $roles = collect($this->input('recipient_roles', []))
                    ->filter()
                    ->values();

                if ($userIds->isEmpty() && $roles->isEmpty()) {
                    $validator->errors()->add('recipient_roles', 'Select at least one recipient or role.');

                    return;
                }

                if ($userIds->isNotEmpty() && $roles->isNotEmpty()) {
                    $validator->errors()->add('recipient_roles', 'Choose either recipient roles or specific recipients, not both.');
                    $validator->errors()->add('recipient_user_ids', 'Choose either recipient roles or specific recipients, not both.');

                    return;
                }

                $matchingRecipients = User::query()
                    ->where(function ($query) use ($userIds, $roles): void {
                        if ($userIds->isNotEmpty()) {
                            $query->whereKey($userIds->all());
                        }

                        if ($roles->isNotEmpty()) {
                            if ($userIds->isNotEmpty()) {
                                $query->orWhereIn('role', $roles->all());
                            } else {
                                $query->whereIn('role', $roles->all());
                            }
                        }
                    })
                    ->exists();

                if (! $matchingRecipients) {
                    $validator->errors()->add('recipient_roles', 'The selected recipients did not match any users.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'recipient_user_ids' => 'recipients',
            'recipient_roles' => 'recipient roles',
        ];
    }
}
