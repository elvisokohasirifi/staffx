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
            'status' => fake()->randomElement([
                TaskStatus::Pending->value,
                TaskStatus::InProgress->value,
                TaskStatus::Completed->value,
                TaskStatus::CouldNotBeAchieved->value,
            ]),
            'sort_order' => fake()->numberBetween(0, 10),
            'outcome_notes' => fake()->optional()->sentence(),
            'admin_id' => User::factory()->admin(),
            'assignee_id' => User::factory()->staff(),
        ];
    }
}
