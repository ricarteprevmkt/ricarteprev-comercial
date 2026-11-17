<?php

namespace App\Livewire\Administrative;

use App\Enums\ContractStatus;
use App\Models\C2Process;
use App\Models\Contract;
use App\Services\C2\C2Client;
use App\Services\C2\C2Exception;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Ficha do processo no INSS. As movimentações são consultadas no C2 na hora
 * (guardadas por 10 minutos), não ficam no banco.
 */
class ProcessShow extends Component
{
    public C2Process $process;

    public function mount(C2Process $process): void
    {
        $this->process = $process->load('lead');
    }

    /** @return array{items: list<array{title: string|null, kind: string, description: string|null, date: CarbonImmutable|null}>, error: string|null} */
    #[Computed]
    public function movements(): array
    {
        if (! C2Client::enabled()) {
            return ['items' => [], 'error' => 'A integração com o C2 está desligada.'];
        }

        try {
            $rows = Cache::remember('c2.movements.'.$this->process->c2_id, 600, fn () => app(C2Client::class)
                ->get('/v1/movimentacoes', ['process_id' => $this->process->c2_id, 'limit' => 100])['items'] ?? []);
        } catch (C2Exception $e) {
            return ['items' => [], 'error' => $e->getMessage()];
        }

        $items = [];
        foreach (is_array($rows) ? $rows : [] as $m) {
            if (is_array($m)) {
                $items[] = [
                    'title' => $this->str($m['title'] ?? null),
                    'kind' => (string) $this->str($m['kind'] ?? null),
                    'description' => $this->str($m['summary'] ?? $m['description'] ?? null),
                    'date' => $this->parse($m['occurred_at'] ?? $m['detected_at'] ?? null),
                ];
            }
        }
        usort($items, fn (array $a, array $b) => ($b['date']->timestamp ?? 0) <=> ($a['date']->timestamp ?? 0));

        return ['items' => $items, 'error' => null];
    }

    /** @return Collection<int, Contract> */
    #[Computed]
    public function contracts(): Collection
    {
        if ($this->process->lead_id === null) {
            return new Collection;
        }

        return Contract::query()->with('executive.user')
            ->where('lead_id', $this->process->lead_id)
            ->where('status', ContractStatus::Signed->value)
            ->latest('signed_at')->get();
    }

    private function str(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function parse(mixed $value): ?CarbonImmutable
    {
        try {
            return is_string($value) && $value !== '' ? CarbonImmutable::parse($value)->setTimezone(config('app.timezone')) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function render(): View
    {
        return view('livewire.administrative.process-show')
            ->title($this->process->client_name ?? 'Processo no INSS');
    }
}
