<?php

namespace App\Models;

use App\Models\Traits\LogsActivity;
use App\Tenancy\BelongsToOrganization;
use App\UserRole;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Database\Factories\EmailNotificationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'subject',
    'body',
    'recipient_user_ids',
    'recipient_roles',
    'recipient_count',
    'sent_by_id',
    'sent_at',
    'organization_id',
])]
class EmailNotification extends Model
{
    use BelongsToOrganization;
    use CrudTrait;

    /** @use HasFactory<EmailNotificationFactory> */
    use HasFactory;

    use HasUuids;
    use LogsActivity;

    public string $identifiableAttribute = 'subject';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'recipient_user_ids' => 'array',
            'recipient_roles' => 'array',
            'recipient_count' => 'integer',
            'sent_at' => 'datetime',
        ];
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_id');
    }

    public function recipientRoleLabels(): string
    {
        $roleOptions = UserRole::options();

        return collect($this->recipient_roles ?? [])
            ->map(fn (string $role): string => $roleOptions[$role] ?? $role)
            ->implode(', ');
    }

    public function recipientUserLabels(): string
    {
        $userIds = collect($this->recipient_user_ids ?? [])
            ->filter()
            ->values();

        if ($userIds->isEmpty()) {
            return '';
        }

        return User::query()
            ->whereKey($userIds->all())
            ->orderBy('name')
            ->get()
            ->map(fn (User $user): string => "{$user->name} ({$user->email})")
            ->implode(', ');
    }
}
