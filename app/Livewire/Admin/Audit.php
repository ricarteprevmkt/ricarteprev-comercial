<?php

namespace App\Livewire\Admin;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Activitylog\Models\Activity;

#[Title('Auditoria')]
class Audit extends Component
{
    use WithPagination;

    #[Url(as: 'area', except: '')]
    public string $logName = '';

    #[Url(as: 'usuario', except: '')]
    public string $causer = '';

    #[Url(as: 'de', except: '')]
    public string $from = '';

    #[Url(as: 'ate', except: '')]
    public string $to = '';

    public ?int $expanded = null;

    /** Nomes legíveis das áreas registradas. */
    public const AREAS = [
        'acesso' => 'Acessos',
        'usuarios' => 'Usuários',
        'perfis' => 'Perfis e permissões',
        'executivos' => 'Executivos',
        'metas' => 'Metas',
        'indicadores' => 'Indicadores',
        'comissoes' => 'Regras de comissão',
        'regioes' => 'Regiões',
        'cidades' => 'Cidades',
        'catalogos' => 'Catálogos',
        'parametros' => 'Parâmetros',
    ];

    /** Rótulos dos eventos padrão. */
    public const EVENTS = [
        'created' => 'criou', 'updated' => 'alterou', 'deleted' => 'excluiu', 'restored' => 'restaurou',
        'login' => 'entrou', 'logout' => 'saiu', 'login_failed' => 'falha de login',
        'role_changed' => 'mudou perfil', 'permissions_changed' => 'mudou permissões',
    ];

    public function mount(): void
    {
        $this->authorize('audit.view');
    }

    public function updating(string $property): void
    {
        if (in_array($property, ['logName', 'causer', 'from', 'to'], true)) {
            $this->resetPage();
        }
    }

    public function toggle(int $id): void
    {
        $this->expanded = $this->expanded === $id ? null : $id;
    }

    /** @return LengthAwarePaginator<int, Activity> */
    #[Computed]
    public function activities(): LengthAwarePaginator
    {
        return Activity::query()
            ->with(['causer', 'subject'])
            ->when($this->logName !== '', fn ($q) => $q->where('log_name', $this->logName))
            ->when($this->causer !== '', fn ($q) => $q->where('causer_type', User::class)->where('causer_id', (int) $this->causer))
            ->when($this->from !== '', fn ($q) => $q->where('created_at', '>=', CarbonImmutable::parse($this->from)->startOfDay()))
            ->when($this->to !== '', fn ($q) => $q->where('created_at', '<=', CarbonImmutable::parse($this->to)->endOfDay()))
            ->latest('id')
            ->paginate(25);
    }

    /**
     * Linhas "campo: antes → depois" de um registro de auditoria.
     *
     * @return Collection<int, array{field: string, old: string, new: string}>
     */
    public static function changes(Activity $activity): Collection
    {
        $props = $activity->properties;
        $new = (array) ($props['attributes'] ?? []);
        $old = (array) ($props['old'] ?? []);

        if ($new === [] && $old === []) {
            return collect($props->all())->map(fn ($v, $k) => [
                'field' => (string) $k, 'old' => '', 'new' => self::stringify($v),
            ])->values();
        }

        return collect(array_unique([...array_keys($new), ...array_keys($old)]))->map(fn ($field) => [
            'field' => (string) $field,
            'old' => self::stringify($old[$field] ?? null),
            'new' => self::stringify($new[$field] ?? null),
        ])->values();
    }

    private static function stringify(mixed $value): string
    {
        return match (true) {
            $value === null => '—',
            is_bool($value) => $value ? 'sim' : 'não',
            is_scalar($value) => (string) $value,
            default => (string) json_encode($value, JSON_UNESCAPED_UNICODE),
        };
    }

    public function render(): View
    {
        return view('livewire.admin.audit', [
            'areas' => self::AREAS,
            'events' => self::EVENTS,
            'users' => User::withTrashed()->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
