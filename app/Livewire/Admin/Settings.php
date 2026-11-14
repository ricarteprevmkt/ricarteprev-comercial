<?php

namespace App\Livewire\Admin;

use App\Models\SystemSetting;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Parâmetros')]
class Settings extends Component
{
    /**
     * Valores do formulário. A chave troca "." por "__", porque o Livewire
     * interpretaria o ponto como um caminho dentro do array.
     *
     * @var array<string, mixed>
     */
    public array $values = [];

    /** Opções fixas para parâmetros de escolha. @var array<string, array<string, string>> */
    private const CHOICES = [
        'finance.margin_revenue_basis' => [
            'expected' => 'Receita prevista dos contratos',
            'received' => 'Receita efetivamente recebida',
        ],
    ];

    /** Limites por parâmetro. @var array<string, list<string>> */
    private const LIMITS = [
        'routes.road_factor' => ['min:1', 'max:3'],
        'routes.cost_per_km' => ['min:0', 'max:100'],
        'funnel.stale_after_days' => ['min:1', 'max:365'],
        'funnel.proposal_followup_days' => ['min:1', 'max:365'],
        'visits.result_due_hours' => ['min:1', 'max:720'],
        'referrals.attribution_window_days' => ['min:0', 'max:3650'],
    ];

    public function mount(): void
    {
        $this->authorize('settings.manage');
        $this->load();
    }

    private function load(): void
    {
        $this->values = SystemSetting::query()->pluck('value', 'key')
            ->mapWithKeys(fn ($v, $k) => [self::field($k) => $v === null ? '' : (string) $v])
            ->all();
    }

    public static function field(string $key): string
    {
        return str_replace('.', '__', $key);
    }

    public function save(): void
    {
        $this->authorize('settings.manage');
        $settings = SystemSetting::query()->get()->keyBy('key');

        $rules = [];
        $attributes = [];
        foreach ($settings as $key => $setting) {
            $field = 'values.'.self::field($key);
            $attributes[$field] = mb_strtolower($setting->label);
            $rules[$field] = match ($setting->type) {
                'int' => ['required', 'integer', ...(self::LIMITS[$key] ?? [])],
                'decimal' => ['nullable', 'decimal:0,4', ...(self::LIMITS[$key] ?? [])],
                'bool' => ['boolean'],
                default => isset(self::CHOICES[$key])
                    ? ['required', 'in:'.implode(',', array_keys(self::CHOICES[$key]))]
                    : ['nullable', 'string', 'max:255'],
            };
        }

        $this->validate($rules, attributes: $attributes);

        DB::transaction(function () use ($settings) {
            foreach ($settings as $key => $setting) {
                $raw = $this->values[self::field($key)] ?? null;
                $value = match ($setting->type) {
                    'int' => (int) $raw,
                    'bool' => (bool) $raw,
                    'decimal' => $raw === '' || $raw === null ? null : (string) $raw,
                    default => $raw === '' ? null : $raw,
                };

                if ($setting->value !== $value) {
                    SystemSetting::put($key, $value, auth()->user()?->id);
                }
            }
        });

        $this->load();
        Flux::toast(variant: 'success', text: 'Parâmetros salvos.');
    }

    public function render(): View
    {
        /** @var Collection<string, Collection<int, SystemSetting>> $groups */
        $groups = SystemSetting::query()->with('updater')->orderBy('group')->orderBy('id')->get()->groupBy('group');

        return view('livewire.admin.settings', [
            'groups' => $groups,
            'choices' => self::CHOICES,
            'groupLabels' => ['rotas' => 'Rotas e deslocamento', 'funil' => 'Funil comercial', 'agenda' => 'Agenda', 'financeiro' => 'Custos e resultados', 'indicacoes' => 'Indicações'],
        ]);
    }
}
