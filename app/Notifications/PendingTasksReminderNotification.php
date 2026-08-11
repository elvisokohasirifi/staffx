<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PendingTasksReminderNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<int, array{title: string, status: string}>  $tasks
     */
    public function __construct(
        public string $staffName,
        public array $tasks,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $taskCount = count($this->tasks);

        $message = (new MailMessage)
            ->subject($taskCount === 1 ? 'Task reminder for today' : 'Tasks reminder for today')
            ->greeting("Hello {$this->staffName},")
            ->line($taskCount === 1
                ? 'You still have 1 task for today that has not been completed yet.'
                : "You still have {$taskCount} tasks for today that have not been completed yet.");

        foreach ($this->tasks as $task) {
            $message->line("{$task['title']} ({$task['status']})");
        }

        return $message
            ->action('View Tasks', backpack_url('tasks'))
            ->line('Please review these tasks and update their progress.');
    }
}
