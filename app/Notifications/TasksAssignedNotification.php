<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TasksAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<int, array{title: string, scheduled_for: string}>  $tasks
     */
    public function __construct(
        public string $staffName,
        public array $tasks,
    ) {
        $this->afterCommit();
    }

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
            ->subject($taskCount === 1 ? 'New task assigned' : 'New tasks assigned')
            ->greeting("Hello {$this->staffName},")
            ->line($taskCount === 1
                ? 'A new task has been assigned to you.'
                : 'New tasks have been assigned to you.');

        foreach ($this->tasks as $task) {
            $message->line("{$task['title']} ({$task['scheduled_for']})");
        }

        return $message
            ->action('View Tasks', backpack_url('tasks'))
            ->line('Please review your tasks and plan your work accordingly.');
    }
}
