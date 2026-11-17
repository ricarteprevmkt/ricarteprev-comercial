<?php

namespace App\Livewire\Administrative;

use App\Enums\InssOutcome;
use App\Models\C2Process;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Lista dos processos no INSS lidos do C2. Por padrão, só os protocolos do
 * escritório; a aba "Fora do escopo" mostra os antigos.
 */
#[Title('Processos no INSS')]
class Processes extends Component
{
    use WithPagination;

    public const FLAGS = [
        'exigencia' => 'Com exigência aberta',
        'agendamento' => 'Com agendamento marcado',
        'recurso' => 'Com prazo de recurso',
        'monitoramento' => 'Sem acompanhamento no C2',
    ];

    #[Url(as: 'busca', except: '')]
    public string $search = '';

    #[Url(as: 'resultado', except: '')]
    public string $outcome = '';

    #[Url(as: 'beneficio', except: '')]
    public string $benefit = '';

    #[Url(as: 'filtro', except: '')]
    public string $flag = '';

    /** '' = do escritório; 'fora' = fora do escopo. */
    #[Url(as: 'escopo', except: '')]
    public string $scope = '';

    public function updating(string $property): void
    {
        if (in_array($property, ['search', 'outcome', 'benefit', 'flag', 'scope'], true)) {
            $this->resetPage();
        }
    }

    /** @return LengthAwarePaginator<int, C2Process> */
    #[Computed]
    public function processes(): LengthAwarePaginator
    {
        $today = today()->toDateString();

        return C2Process::query()
            ->where('in_scope', $this->scope !== 'fora')
            ->when(InssOutcome::tryFrom($this->outcome), fn ($q, InssOutcome $o) => $q->where('outcome', $o->value))
            ->when($this->benefit !== '', fn ($q) => $q->where('benefit_type', $this->benefit))
            ->when($this->flag === 'exigencia', fn ($q) => $q->openRequirement())
            ->when($this->flag === 'agendamento', fn ($q) => $q->where('appointment_date', '>=', $today))
            ->when($this->flag === 'recurso', fn ($q) => $q->appealOpen())
            ->when($this->flag === 'monitoramento', fn ($q) => $q->monitoringProblem())
            ->when(trim($this->search) !== '', function ($q) {
                $term = '%'.trim($this->search).'%';
                $q->where(fn ($q) => $q->where('client_name', 'like', $term)
                    ->orWhere('request_number', 'like', $term)
                    ->orWhere('benefit_number', 'like', $term));
            })
            ->orderByRaw('opened_at is null')->orderByDesc('opened_at')->orderBy('client_name')
            ->paginate(25);
    }

    public function render(): View
    {
        return view('livewire.administrative.processes', [
            'outcomes' => InssOutcome::cases(),
            'benefits' => C2Process::query()->whereNotNull('benefit_type')->distinct()->orderBy('benefit_type')->pluck('benefit_type'),
            'outOfScope' => C2Process::query()->where('in_scope', false)->count(),
        ]);
    }
}
