<?php

namespace Database\Factories;

use App\Models\DetectionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DetectionPlan>
 */
class DetectionPlanFactory extends Factory
{
    protected $model = DetectionPlan::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'strategy' => fake()->randomElement(['fast', 'balanced', 'strict']),
            'socket_scope' => fake()->optional(0.45)->numberBetween(1, 3),
            'window_samples' => fake()->numberBetween(45, 140),
            'min_samples' => fake()->numberBetween(3, 8),
            'match_threshold' => fake()->numberBetween(62, 82),
            'is_active' => false,
            'notes' => fake()->optional(0.55)->sentence(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (): array => ['is_active' => true]);
    }
}
