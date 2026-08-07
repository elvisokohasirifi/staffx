<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TasksApprovedNotification extends Notification implements ShouldQueue
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
            ->subject($taskCount === 1 ? 'Task completion approved' : 'Task completions approved')
            ->greeting("Hello {$this->staffName},")
            ->line($taskCount === 1
                ? 'Your completed task has been approved.'
                : 'Your completed tasks have been approved.');

        foreach ($this->tasks as $task) {
            $message->line("{$task['title']} ({$task['scheduled_for']})");
        }

        return $message
            ->action('View Tasks', backpack_url('tasks'))
            ->line('Keep up the good work.');
    }
}
