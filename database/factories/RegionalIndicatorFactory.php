<?php

namespace Database\Factories;

use App\Enums\IndicatorStatus;
use App\Models\RegionalIndicator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RegionalIndicator>
 */
class RegionalIndicatorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'whatsapp' => '889'.fake()->numerify('########'),
            'registered_at' => now()->subMonths(2),
            'status' => IndicatorStatus::Active,
        ];
    }
}
