<?php

namespace Database\Factories;

use App\Enums\LeadStatus;
use App\Models\Executive;
use App\Models\Lead;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    public function definition(): array
    {
        return [
            'type' => 'pf',
            'name' => fake()->name(),
            'phone' => '889'.fake()->unique()->numerify('########'),
            'executive_id' => Executive::factory(),
            'entered_at' => now()->subDays(3),
            'status' => LeadStatus::Active,
        ];
    }
}
