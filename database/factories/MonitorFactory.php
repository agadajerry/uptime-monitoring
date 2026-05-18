<?php

namespace Database\Factories;

use App\Models\Monitor;
use Illuminate\Database\Eloquent\Factories\Factory;

class MonitorFactory extends Factory
{
    protected $model = Monitor::class;

    public function definition(): array
    {
        return [
            'url' => $this->faker->unique()->url(),
            'check_interval' => $this->faker->numberBetween(1, 60),
            'threshold' => $this->faker->numberBetween(1, 5),
            'status' => $this->faker->randomElement(['pending', 'up', 'down']),
            'last_checked_at' => $this->faker->optional()->dateTimeBetween('-1 hour', 'now'),
        ];
    }
}
