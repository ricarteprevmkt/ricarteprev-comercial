<?php

namespace App\Actions\Agenda;

use App\Actions\Crm\MoveOpportunity;
use App\Enums\OpportunityStage;
use App\Enums\VisitType;
use App\Models\City;
use App\Models\Executive;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\User;
use App\Models\Visit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cria ou altera o agendamento de uma visita e recalcula as rotas afetadas.
 *
 * Localização, em ordem de preferência: ponto marcado no mapa, ponto salvo
 * no contato, centro da cidade (aproximado).
 *
 * Agendar um "Atendimento agendado" para uma oportunidade que ainda não
 * chegou a essa etapa avança a oportunidade para "Atendimento agendado".
 */
final class SaveVisit
{
    public function __construct(private BuildRoute $routes, private MoveOpportunity $move) {}

    /**
     * @param  array{executive_id: int, lead_id?: int|null, opportunity_id?: int|null, contact_name?: string|null,
     *     type: VisitType, priority: string, scheduled_at: string, address?: string|null, city_id?: int|null,
     *     neighborhood?: string|null, latitude?: string|float|null, longitude?: string|float|null, notes?: string|null}  $data
     */
    public function handle(?Visit $visit, array $data, ?User $user, bool $saveLocationOnLead = false): Visit
    {
        $lead = isset($data['lead_id']) ? Lead::query()->find($data['lead_id']) : null;
        $opportunity = isset($data['opportunity_id']) ? Opportunity::query()->find($data['opportunity_id']) : null;

        if ($lead === null && empty($data['contact_name'])) {
            throw ValidationException::withMessages(['form.lead_id' => 'Escolha um contato cadastrado ou informe o nome de quem será visitado.']);
        }

        if ($opportunity && $lead && $opportunity->lead_id !== $lead->id) {
            throw ValidationException::withMessages(['form.opportunity_id' => 'A oportunidade não pertence a este contato.']);
        }

        if ($visit && ! $visit->status->isPending()) {
            throw ValidationException::withMessages(['form.scheduled_at' => 'Só visitas ainda não realizadas podem ser alteradas.']);
        }

        [$lat, $lng, $source] = $this->resolveLocation($data, $lead);

        return DB::transaction(function () use ($visit, $data, $user, $lead, $opportunity, $lat, $lng, $source, $saveLocationOnLead) {
            $creating = $visit === null;
            $previous = $visit ? [$visit->executive_id, $visit->scheduled_at] : null;

            $visit ??= new Visit(['created_by' => $user?->id]);
            $visit->fill([
                'executive_id' => $data['executive_id'],
                'lead_id' => $lead?->id,
                'opportunity_id' => $opportunity?->id,
                'contact_name' => $lead ? null : ($data['contact_name'] ?? null),
                'type' => $data['type'],
                'priority' => $data['priority'],
                'scheduled_at' => $data['scheduled_at'],
                'address' => $data['address'] ?? null,
                'city_id' => $data['city_id'] ?? null,
                'neighborhood' => $data['neighborhood'] ?? null,
                'latitude' => $lat,
                'longitude' => $lng,
                'location_source' => $source,
                'notes' => $data['notes'] ?? null,
            ]);
            $visit->save();

            if ($saveLocationOnLead && $lead && $source === 'manual') {
                $lead->forceFill(['latitude' => $lat, 'longitude' => $lng])->save();
            }

            if ($creating && $opportunity && $data['type'] === VisitType::Appointment
                && ! $opportunity->stage->isClosed()
                && ($opportunity->stage->rank() ?? 0) < (int) OpportunityStage::Scheduled->rank()) {
                $this->move->handle($opportunity, OpportunityStage::Scheduled, $user, 'Atendimento agendado para '.$visit->scheduled_at->format('d/m/Y H:i'));
            } elseif ($opportunity) {
                $opportunity->forceFill(['last_activity_at' => now()])->save();
            }

            $this->rebuild($visit->executive_id, $visit->scheduled_at);
            if ($previous && ($previous[0] !== $visit->executive_id || ! $previous[1]->isSameDay($visit->scheduled_at))) {
                $this->rebuild($previous[0], $previous[1]);
            }

            return $visit;
        });
    }

    /** Recalcula a rota depois que a gravação for confirmada (a rota pode consultar um serviço externo). */
    public function rebuild(int $executiveId, CarbonImmutable $date): void
    {
        DB::afterCommit(function () use ($executiveId, $date) {
            $executive = Executive::withTrashed()->find($executiveId);

            if ($executive) {
                $this->routes->handle($executive, $date);
            }
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: string|null, 1: string|null, 2: string|null}
     */
    private function resolveLocation(array $data, ?Lead $lead): array
    {
        if (is_numeric($data['latitude'] ?? null) && is_numeric($data['longitude'] ?? null)) {
            $sameAsLead = $lead && $lead->latitude !== null
                && abs((float) $lead->latitude - (float) $data['latitude']) < 0.000001
                && abs((float) $lead->longitude - (float) $data['longitude']) < 0.000001;

            return [(string) $data['latitude'], (string) $data['longitude'], $sameAsLead ? 'lead' : 'manual'];
        }

        if ($lead && $lead->latitude !== null && $lead->longitude !== null
            && (empty($data['city_id']) || (int) $data['city_id'] === $lead->city_id)) {
            return [$lead->latitude, $lead->longitude, 'lead'];
        }

        if (! empty($data['city_id'])) {
            $city = City::query()->find((int) $data['city_id']);
            if ($city && $city->latitude !== null) {
                return [$city->latitude, $city->longitude, 'city'];
            }
        }

        return [null, null, null];
    }
}
