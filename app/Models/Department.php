<?php

namespace App\Models;

use App\Tenancy\BelongsToOrganization;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Database\Factories\DepartmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'organization_id'])]
class Department extends Model
{
    use BelongsToOrganization;
    use CrudTrait;

    /** @use HasFactory<DepartmentFactory> */
    use HasFactory;

    use HasUuids;

    public function administrators(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'department_administrators');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
