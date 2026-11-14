<?php

namespace Database\Factories;

use App\Enums\OpportunityStage;
use App\Models\Lead;
use App\Models\Opportunity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Opportunity>
 */
class OpportunityFactory extends Factory
{
    public function definition(): array
    {
        return [
            'lead_id' => Lead::factory(),
            'executive_id' => fn (array $attributes) => Lead::query()->whereKey($attributes['lead_id'])->value('executive_id'),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Opportunity $opportunity) {
            $opportunity->stage ??= OpportunityStage::Contact;
            $opportunity->furthest_stage_rank = max($opportunity->furthest_stage_rank ?? 0, $opportunity->stage->rank() ?? 1);
            $opportunity->last_activity_at ??= now();
            $opportunity->stage_changed_at ??= now();
        });
    }

    public function stage(OpportunityStage $stage, ?int $furthest = null): static
    {
        return $this->afterMaking(function (Opportunity $opportunity) use ($stage, $furthest) {
            $opportunity->stage = $stage;
            $opportunity->furthest_stage_rank = $furthest ?? $stage->rank() ?? 1;
        });
    }
}
