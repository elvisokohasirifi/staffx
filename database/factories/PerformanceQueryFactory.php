<?php

namespace Database\Factories;

use App\Models\PerformanceQuery;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PerformanceQuery>
 */
class PerformanceQueryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'admin_id' => User::factory()->admin(),
            'question' => 'Which staff person was the best performer in March 2026?',
            'chart_type' => 'bar',
            'result_data' => [
                'answer' => 'No staff performance data is available for this period.',
                'metric_label' => 'Approved completed tasks',
                'period_label' => 'March 2026',
                'chart' => [
                    'labels' => [],
                    'datasets' => [],
                ],
            ],
        ];
    }
}
