<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class AdminEmailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $subjectLine,
        public string $messageBody,
    ) {
        $this->afterCommit();
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject($this->subjectLine)
            ->greeting('Hello '.($notifiable->name ?? 'there').',');

        collect(preg_split('/(\r\n|\r|\n){2,}/', trim($this->messageBody)) ?: [])
            ->map(fn (string $paragraph): string => trim($paragraph))
            ->filter()
            ->each(function (string $paragraph) use ($message): void {
                $message->line(new HtmlString(nl2br(e($paragraph))));
            });

        return $message;
    }
}
