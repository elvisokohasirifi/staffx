<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\TaskRemark;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskRemark>
 */
class TaskRemarkFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'author_id' => User::factory()->staff(),
            'parent_remark_id' => null,
            'body' => fake()->paragraph(),
            'is_admin_remark' => false,
        ];
    }
}
