<?php

namespace App\Tenancy;

use App\Models\Organization;
use App\Models\User;
use App\UserRole;
use Illuminate\Support\Facades\DB;

class RegisterOrganizationAction
{
    /**
     * @param  array{organization_name: string, name: string, email: string, password: string}  $attributes
     */
    public function execute(array $attributes): User
    {
        return DB::transaction(function () use ($attributes): User {
            $organization = Organization::query()->create([
                'name' => $attributes['organization_name'],
                'is_default' => false,
            ]);

            $user = User::query()->create([
                'name' => $attributes['name'],
                'email' => $attributes['email'],
                'password' => $attributes['password'],
                'role' => UserRole::Admin,
                'organization_id' => $organization->getKey(),
            ]);

            $organization->update(['owner_id' => $user->getKey()]);

            return $user;
        });
    }
}
