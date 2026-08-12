<?php

namespace App\Actions\Notifications;

use App\Models\EmailNotification;
use App\Models\User;
use App\Notifications\AdminEmailNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Notification;

class SendAdminEmailNotificationAction
{
    public function send(EmailNotification $emailNotification): int
    {
        $recipients = $this->resolveRecipients($emailNotification);

        if ($recipients->isEmpty()) {
            return 0;
        }

        Notification::send($recipients, new AdminEmailNotification(
            subjectLine: $emailNotification->subject,
            messageBody: $emailNotification->body,
        ));

        return $recipients->count();
    }

    /**
     * @return EloquentCollection<int, User>
     */
    private function resolveRecipients(EmailNotification $emailNotification): EloquentCollection
    {
        $userIds = collect($emailNotification->recipient_user_ids ?? [])
            ->filter()
            ->values();
        $roles = collect($emailNotification->recipient_roles ?? [])
            ->filter()
            ->values();

        if ($userIds->isEmpty() && $roles->isEmpty()) {
            return new EloquentCollection;
        }

        return User::query()
            ->where(function (Builder $query) use ($userIds, $roles): void {
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
            ->orderBy('name')
            ->get();
    }
}
