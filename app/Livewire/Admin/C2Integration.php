<?php

namespace App\Livewire\Admin;

use App\Actions\C2\SyncC2Processes;
use App\Enums\InssOutcome;
use App\Models\C2Process;
use App\Models\IntegrationState;
use App\Services\C2\C2Client;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Situação da integração com o C2 Prev e clientes do C2 que não foram ligados
 * a nenhum contato (CPF ausente ou diferente no nosso cadastro).
 */
#[Title('Integração C2 Prev')]
class C2Integration extends Component
{
    use WithPagination;

    /** @return LengthAwarePaginator<int, C2Process> */
    #[Computed]
    public function unmatched(): LengthAwarePaginator
    {
        return C2Process::query()->whereNull('lead_id')->orderBy('client_name')->paginate(20);
    }

    public function render(): View
    {
        $lastSync = IntegrationState::get(SyncC2Processes::LAST_SYNC);
        $counts = C2Process::query()->selectRaw('outcome, count(*) as total')->groupBy('outcome')->pluck('total', 'outcome');

        return view('livewire.admin.c2-integration', [
            'enabled' => C2Client::enabled(),
            'backfillDone' => (bool) IntegrationState::get(SyncC2Processes::BACKFILL_DONE, false),
            'lastSync' => $lastSync ? CarbonImmutable::parse((string) $lastSync)->setTimezone(config('app.timezone')) : null,
            'lastError' => IntegrationState::get(SyncC2Processes::LAST_ERROR),
            'total' => (int) $counts->sum(),
            'linked' => C2Process::query()->whereNotNull('lead_id')->count(),
            'counts' => collect(InssOutcome::cases())->map(fn (InssOutcome $o) => ['outcome' => $o, 'total' => (int) ($counts[$o->value] ?? 0)]),
        ]);
    }
}
