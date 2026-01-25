<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Task>
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
            'user_id' => \App\Models\User::factory(),
            'title' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
            'due_at' => $this->faker->dateTimeBetween('now', '+1 month')->format('Y-m-d H:i:00'),
            'is_active' => true,
        ];
    }

    public function recurring($frequency = 'daily', $interval = 1)
    {
        return $this->state(fn (array $attributes) => [
            'recurrence_rule' => [
                'frequency' => $frequency,
                'interval' => $interval,
            ],
        ]);
    }
}
