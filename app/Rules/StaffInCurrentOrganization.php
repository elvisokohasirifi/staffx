<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class StaffInCurrentOrganization implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $staffExists = is_string($value)
            && User::query()->staff()->whereKey($value)->exists();

        if (! $staffExists) {
            $fail('The selected staff member does not belong to your organization.');
        }
    }
}
