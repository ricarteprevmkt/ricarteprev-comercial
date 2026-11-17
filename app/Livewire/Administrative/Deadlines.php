<?php

namespace App\Livewire\Administrative;

use App\Metrics\InssDeadlines;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Agenda dos prazos: exigências, agendamentos e prazos de recurso, do mais
 * próximo para o mais distante. Exigências vencidas aparecem no topo.
 */
#[Title('Prazos no INSS')]
class Deadlines extends Component
{
    public const WINDOWS = ['7' => 'Próximos 7 dias', '30' => 'Próximos 30 dias', '90' => 'Próximos 90 dias', 'todos' => 'Todos'];

    #[Url(as: 'tipo', except: '')]
    public string $type = '';

    #[Url(as: 'dias', except: '30')]
    public string $window = '30';

    public function render(): View
    {
        $days = $this->window === 'todos' ? null : (int) ($this->window ?: 30);
        $type = array_key_exists($this->type, InssDeadlines::TYPES) ? $this->type : null;

        return view('livewire.administrative.deadlines', [
            'items' => (new InssDeadlines)->list($days, $type),
            'types' => InssDeadlines::TYPES,
        ]);
    }
}
