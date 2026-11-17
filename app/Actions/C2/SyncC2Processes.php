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

        return [
            'synced' => $this->synced,
            'linked' => $this->relink(),
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
                    $this->syncProcess($id);
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
                if ($id !== '' && ! isset($this->seen[$id])) {
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

    private function syncProcess(string $id): void
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
            'request_number' => $data['request_number'] ?? null,
            'benefit_type' => $this->limit($data['benefit_type'] ?? $data['service'] ?? null),
            'status_text' => $this->limit($status),
            'decision_text' => $this->limit($decision),
            'outcome' => InssOutcome::classify($decision, $status, $concludedAt !== null),
            'opened_at' => $this->date($data['opened_at'] ?? null),
            'concluded_at' => $concludedAt,
            'synced_at' => now(),
        ]);

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

    private function date(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    private function limit(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? mb_substr($value, 0, 255) : null;
    }
}
