<?php

namespace App\Actions\C2;

use App\Enums\InssOutcome;
use App\Models\C2Process;
use App\Models\IntegrationState;
use App\Models\Lead;
use App\Services\C2\C2Client;
use App\Services\C2\C2Exception;
use App\Support\Cpf;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Traz do C2 Prev o resultado dos processos no INSS e liga cada um ao contato
 * pelo CPF. Roda pelo agendador a cada 5 minutos.
 *
 * 1. Carga inicial: percorre os monitoramentos (um por processo) e lê cada
 *    processo. Feita uma vez, em várias rodadas se precisar.
 * 2. Feed: lê só os eventos novos e atualiza os processos que mudaram.
 * 3. Religa processos sem contato, para o caso de o CPF ter sido corrigido.
 * 4. Separa os protocolos antigos (RefreshC2Scope).
 *
 * Cada rodada usa no máximo $budget consultas (a API aceita 120/min). Se a
 * cota acabar no meio de uma página, a página é relida na próxima rodada.
 */
final class SyncC2Processes
{
    public const BACKFILL_DONE = 'c2.backfill_done';

    public const BACKFILL_CURSOR = 'c2.backfill_cursor';

    public const BACKFILL_STARTED = 'c2.backfill_started_at';

    public const EVENTS_CURSOR = 'c2.events_cursor';

    public const LAST_SYNC = 'c2.last_sync_at';

    public const LAST_ERROR = 'c2.last_error';

    private const PAGE = 50;

    private int $synced = 0;

    /** @var array<string, true> processos já lidos nesta rodada */
    private array $seen = [];

    public function __construct(private C2Client $client, private int $budget = 90) {}

    /** @return array{synced: int, linked: int, requests: int, error: string|null, backfill_done: bool} */
    public function run(): array
    {
        $error = null;

        try {
            if (! IntegrationState::get(self::BACKFILL_DONE, false)) {
                $this->backfill();
            }
            if (IntegrationState::get(self::BACKFILL_DONE, false)) {
                $this->feed();
            }
            IntegrationState::put(self::LAST_SYNC, now()->toIso8601String());
            IntegrationState::put(self::LAST_ERROR, null);
        } catch (BudgetExhausted) {
            // Continua na próxima rodada.
            IntegrationState::put(self::LAST_SYNC, now()->toIso8601String());
        } catch (C2Exception $e) {
            $error = $e->getMessage();
            IntegrationState::put(self::LAST_ERROR, ['message' => $error, 'at' => now()->toIso8601String()]);
        }

        $linked = $this->relink();
        app(RefreshC2Scope::class)->handle();

        return [
            'synced' => $this->synced,
            'linked' => $linked,
            'requests' => $this->client->requests,
            'error' => $error,
            'backfill_done' => (bool) IntegrationState::get(self::BACKFILL_DONE, false),
        ];
    }

    private function backfill(): void
    {
        if (IntegrationState::get(self::BACKFILL_STARTED) === null) {
            IntegrationState::put(self::BACKFILL_STARTED, now()->toIso8601String());
        }
        $startedAt = CarbonImmutable::parse((string) IntegrationState::get(self::BACKFILL_STARTED));

        do {
            $cursor = IntegrationState::get(self::BACKFILL_CURSOR);
            $page = $this->call('/v1/monitoramentos', ['limit' => self::PAGE, 'cursor' => $cursor]);

            foreach ($page['items'] ?? [] as $item) {
                $id = (string) ($item['process_id'] ?? '');
                // Processo já lido desde o início da carga: não consulta de novo ao reler a página.
                if ($id !== '' && ! C2Process::query()->where('c2_id', $id)->where('synced_at', '>=', $startedAt)->exists()) {
                    $this->syncProcess($id, $item);
                }
            }

            $next = $page['next_cursor'] ?? null;
            if ($next !== null) {
                IntegrationState::put(self::BACKFILL_CURSOR, $next);
            }
        } while (($page['has_more'] ?? false) && $next !== null);

        IntegrationState::put(self::BACKFILL_DONE, true);
    }

    private function feed(): void
    {
        do {
            $cursor = IntegrationState::get(self::EVENTS_CURSOR);

            try {
                $page = $this->call('/v1/eventos', ['source' => 'meu_inss', 'limit' => self::PAGE, 'cursor' => $cursor]);
            } catch (C2Exception $e) {
                if ($e->status !== 410) {
                    throw $e;
                }
                // Cursor expirado (mais de 30 dias sem ler): recomeça o feed.
                IntegrationState::put(self::EVENTS_CURSOR, null);

                return;
            }

            foreach ($page['items'] ?? [] as $event) {
                $id = (string) ($event['process_id'] ?? '');
                $type = (string) ($event['type'] ?? '');

                if ($id !== '' && str_starts_with($type, 'monitoring.')) {
                    $this->updateMonitoring($id, $type === 'monitoring.recovered' ? 'active' : 'action_required');
                } elseif ($id !== '' && ! isset($this->seen[$id])) {
                    $this->syncProcess($id);
                }
            }

            // A documentação pede para gravar o cursor sempre, mesmo com a página vazia.
            $next = $page['next_cursor'] ?? null;
            if ($next !== null) {
                IntegrationState::put(self::EVENTS_CURSOR, $next);
            }
        } while (($page['has_more'] ?? false) && $next !== null);
    }

    /**
     * O evento de monitoramento não traz o motivo: na falha, consulta o
     * monitoramento do processo para saber se é senha inválida ou autenticação.
     */
    private function updateMonitoring(string $processId, string $state): void
    {
        $action = null;

        if ($state !== 'active') {
            $item = $this->call('/v1/monitoramentos', ['process_id' => $processId, 'limit' => 1])['items'][0] ?? null;
            $state = is_array($item) ? (string) ($item['state'] ?? $state) : $state;
            $action = is_array($item) ? ($item['action_required'] ?? null) : null;
        }

        C2Process::query()->where('c2_id', $processId)->update(['monitoring_state' => $state, 'monitoring_action' => $action]);
    }

    /** @param  array<string, mixed>|null  $monitoring  item de /v1/monitoramentos, na carga inicial */
    private function syncProcess(string $id, ?array $monitoring = null): void
    {
        try {
            $data = $this->call('/v1/processos/'.rawurlencode($id));
        } catch (C2Exception $e) {
            if ($e->status === 404 || $e->status === 403) {
                return; // Fora do escritório ou removido: ignora.
            }
            throw $e;
        }
        $this->seen[$id] = true;

        $cpf = Cpf::digits($data['client']['cpf'] ?? null);
        $hash = strlen($cpf) === 11 ? Cpf::hash($cpf) : null;
        $decision = $data['decision']['status'] ?? null;
        $status = $data['classified_status'] ?? $data['source_status'] ?? null;
        $concludedAt = $this->date($data['concluded_at'] ?? null);

        $process = C2Process::query()->firstOrNew(['c2_id' => (string) $data['id']]);
        $process->fill([
            'c2_client_id' => (string) ($data['client']['id'] ?? ''),
            'client_name' => $data['client']['name'] ?? null,
            'cpf_hash' => $hash,
            'cpf_last_digits' => $hash ? substr($cpf, -2) : null,
            'request_number' => $this->limit($data['request_number'] ?? null, 60),
            'benefit_number' => $this->limit($data['benefit_number'] ?? null, 60),
            'benefit_type' => $this->limit($data['benefit_type'] ?? $data['service'] ?? null),
            'service' => $this->limit($data['service'] ?? null),
            'status_text' => $this->limit($status),
            'decision_text' => $this->limit($decision),
            'requirement_text' => $this->text($data['requirement']['description'] ?? null),
            'requirement_deadline' => $this->date($data['requirement']['deadline'] ?? null),
            'appointment_date' => $this->date($data['appointment']['date'] ?? null),
            'appointment_time' => $this->limit($data['appointment']['time'] ?? null, 10),
            'appointment_location' => $this->limit($data['appointment']['location'] ?? null),
            'decision_reason' => $this->text($data['decision']['reason'] ?? null),
            'appeal_deadline' => $this->date($data['decision']['appeal_deadline'] ?? null),
            'outcome' => InssOutcome::classify($decision, $status, $concludedAt !== null),
            'opened_at' => $this->date($data['opened_at'] ?? null),
            'concluded_at' => $concludedAt,
            'checked_at' => $this->dateTime($data['last_checked_at'] ?? null),
            'synced_at' => now(),
        ]);

        if ($monitoring !== null) {
            $process->monitoring_state = $this->limit($monitoring['state'] ?? null, 30);
            $process->monitoring_action = $this->limit($monitoring['action_required'] ?? null, 40);
        }

        if ($process->lead_id === null && $hash !== null) {
            $process->lead_id = $this->leadFor($hash);
        }

        $process->save();
        $this->synced++;
    }

    /** Liga ao contato os processos que ainda não têm, pelo hash do CPF. */
    private function relink(): int
    {
        $linked = 0;

        C2Process::query()->whereNull('lead_id')->whereNotNull('cpf_hash')->each(function (C2Process $process) use (&$linked) {
            if ($leadId = $this->leadFor((string) $process->cpf_hash)) {
                $process->update(['lead_id' => $leadId]);
                $linked++;
            }
        });

        return $linked;
    }

    private function leadFor(string $hash): ?int
    {
        $id = Lead::query()->where('document_hash', $hash)->orderByDesc('id')->value('id');

        return $id === null ? null : (int) $id;
    }

    /**
     * @param  array<string, scalar|null>  $query
     * @return array<string, mixed>
     */
    private function call(string $path, array $query = []): array
    {
        if ($this->client->requests >= $this->budget) {
            throw new BudgetExhausted;
        }

        return $this->client->get($path, $query);
    }

    /** Aceita ISO (2026-11-20, 2026-11-20T10:00:00Z) e o formato brasileiro (20/11/2026). */
    private function date(mixed $value): ?string
    {
        return $this->parse($value)?->toDateString();
    }

    private function dateTime(mixed $value): ?CarbonImmutable
    {
        return $this->parse($value);
    }

    private function parse(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            if (preg_match('#^(\d{2})/(\d{2})/(\d{4})#', trim($value), $m)) {
                return CarbonImmutable::createFromFormat('!d/m/Y', "{$m[1]}/{$m[2]}/{$m[3]}") ?: null;
            }

            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    private function limit(mixed $value, int $max = 255): ?string
    {
        return is_string($value) && trim($value) !== '' ? mb_substr(trim($value), 0, $max) : null;
    }

    private function text(mixed $value): ?string
    {
        return $this->limit($value, 5000);
    }
}
