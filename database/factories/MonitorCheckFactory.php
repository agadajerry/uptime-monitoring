<?php

namespace Database\Factories;

use App\Models\Monitor;
use App\Models\MonitorCheck;
use Illuminate\Database\Eloquent\Factories\Factory;

class MonitorCheckFactory extends Factory
{
    protected $model = MonitorCheck::class;

    public function definition(): array
    {
        $isUp = $this->faker->boolean(85); // 85% uptime by default

        return [
            'monitor_id' => Monitor::factory(),
            'status_code' => $isUp
                ? $this->faker->randomElement([200, 201, 301, 302])
                : $this->faker->randomElement([0, 500, 503, 504]),
            'response_time_ms' => $isUp ? $this->faker->numberBetween(50, 2000) : null,
            'is_up' => $isUp,
            'checked_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
        ];
    }
}
