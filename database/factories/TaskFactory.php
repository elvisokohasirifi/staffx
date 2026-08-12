<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\User;
use App\TaskStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'scheduled_for' => fake()->dateTimeBetween('-1 week', '+2 weeks')->format('Y-m-d'),
            'scheduled_time' => fake()->randomElement(['09:00:00', '12:30:00', '17:45:00', '23:59:00']),
            'status' => fake()->randomElement([
                TaskStatus::Pending->value,
                TaskStatus::InProgress->value,
                TaskStatus::Completed->value,
                TaskStatus::CouldNotBeAchieved->value,
            ]),
            'approved_as_completed' => false,
            'is_admin_personal' => false,
            'sort_order' => fake()->numberBetween(0, 10),
            'outcome_notes' => fake()->optional()->sentence(),
            'admin_id' => User::factory()->admin(),
            'assignee_id' => User::factory()->staff(),
        ];
    }

    public function adminPersonal(?User $admin = null): static
    {
        return $this->state(function () use ($admin): array {
            $ownerId = $admin?->getKey() ?? User::factory()->admin()->create()->getKey();

            return [
                'is_admin_personal' => true,
                'admin_id' => $ownerId,
                'assignee_id' => $ownerId,
            ];
        });
    }
}
