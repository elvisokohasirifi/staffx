<?php

namespace Database\Factories;

use App\Models\RecurringTask;
use App\Models\User;
use App\RecurringTaskPattern;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecurringTask>
 */
class RecurringTaskFactory extends Factory
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
            'description' => fake()->optional()->paragraph(),
            'scheduled_time' => fake()->randomElement(['09:00:00', '12:30:00', '17:45:00', '23:59:00']),
            'repeat_pattern' => fake()->randomElement(array_keys(RecurringTaskPattern::options())),
            'is_active' => true,
            'admin_id' => User::factory()->admin(),
            'assignee_id' => User::factory()->staff(),
        ];
    }
}
