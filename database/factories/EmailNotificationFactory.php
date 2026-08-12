<?php

namespace Database\Factories;

use App\Models\EmailNotification;
use App\Models\User;
use App\UserRole;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailNotification>
 */
class EmailNotificationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subject' => fake()->sentence(4),
            'body' => fake()->paragraphs(2, true),
            'recipient_user_ids' => [],
            'recipient_roles' => [UserRole::Staff->value],
            'recipient_count' => 0,
            'sent_by_id' => User::factory()->admin(),
            'sent_at' => now(),
        ];
    }
}
