<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class StaffAccountInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
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
        $resetPasswordUrl = route('backpack.auth.password.reset');

        return (new MailMessage)
            ->subject('Welcome to StaffX')
            ->greeting('Hello '.($notifiable->name ?? 'there').',')
            ->line('You have been added to your company\'s StaffX staff management platform.')
            ->line('Sign in to access your tasks and schedule.')
            ->action('Sign In', backpack_url('login'))
            ->line(new HtmlString('If you do not know your password, <a href="'.e($resetPasswordUrl).'">reset it</a> to access your account.'));
    }
}
