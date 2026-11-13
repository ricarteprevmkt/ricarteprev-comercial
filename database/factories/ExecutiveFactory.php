<?php

namespace Database\Factories;

use App\Enums\ExecutiveStatus;
use App\Models\Executive;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Executive>
 */
class ExecutiveFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => ExecutiveStatus::Active,
            'hired_at' => fake()->dateTimeBetween('-3 years', '-1 month'),
            'monthly_visits_target' => 60,
            'monthly_contracts_target' => 12,
        ];
    }
}
