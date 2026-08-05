<?php

namespace App\Models;

use App\UserRole;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use CrudTrait;

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    use HasUuids;

    public string $identifiableAttribute = 'name';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            if (empty($user->role)) {
                $user->role = self::query()->where('role', UserRole::Admin->value)->exists()
                    ? UserRole::Staff
                    : UserRole::Admin;
            }
        });
    }

    public function createdTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'admin_id');
    }

    public function assignedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assignee_id');
    }

    public function taskRemarks(): HasMany
    {
        return $this->hasMany(TaskRemark::class, 'author_id');
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isStaff(): bool
    {
        return $this->role === UserRole::Staff;
    }

    public function avatar(): string
    {
        return 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==';
    }

    public function scopeAdmins($query)
    {
        return $query->where('role', UserRole::Admin->value);
    }

    public function scopeStaff($query)
    {
        return $query->where('role', UserRole::Staff->value);
    }
}
