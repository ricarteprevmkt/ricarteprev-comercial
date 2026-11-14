<?php

namespace Database\Factories;

use App\Enums\VisitStatus;
use App\Enums\VisitType;
use App\Models\Executive;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Visit>
 */
class VisitFactory extends Factory
{
    public function definition(): array
    {
        return [
            'executive_id' => Executive::factory(),
            'contact_name' => fake()->name(),
            'type' => VisitType::FieldVisit,
            'priority' => 'normal',
            'status' => VisitStatus::Scheduled,
            'scheduled_at' => now()->setTime(9, 0),
        ];
    }
}
