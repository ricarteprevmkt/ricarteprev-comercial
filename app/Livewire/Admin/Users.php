<?php

namespace App\Livewire\Admin;

use App\Actions\Users\SaveUser;
use App\Enums\Role;
use App\Models\User;
use App\Rules\BrazilianPhone;
use App\Support\Phone;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Usuários')]
class Users extends Component
{
    use WithPagination;

    #[Url(as: 'busca', except: '')]
    public string $search = '';

    #[Url(as: 'perfil', except: '')]
    public string $roleFilter = '';

    #[Url(as: 'status', except: '')]
    public string $statusFilter = '';

    public ?int $editingId = null;

    /** @var array{name: string, email: string, phone: string, role: string, active: bool, password: string} */
    public array $form = ['name' => '', 'email' => '', 'phone' => '', 'role' => 'gestor', 'active' => true, 'password' => ''];

    public function mount(): void
    {
        $this->authorize('users.manage');
    }

    public function updating(string $property): void
    {
        if (in_array($property, ['search', 'roleFilter', 'statusFilter'], true)) {
            $this->resetPage();
        }
    }

    public function create(): void
    {
        $this->resetForm();
        Flux::modal('user-form')->show();
    }

    public function edit(int $id): void
    {
        $user = User::query()->findOrFail($id);
        $this->resetValidation();
        $this->editingId = $user->id;
        $this->form = [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => Phone::format($user->phone),
            'role' => $user->primaryRole()->value ?? Role::Manager->value,
            'active' => $user->active,
            'password' => '',
        ];
        Flux::modal('user-form')->show();
    }

    public function save(SaveUser $action): void
    {
        $this->authorize('users.manage');
        $user = $this->editingId ? User::query()->findOrFail($this->editingId) : null;

        $this->validate([
            'form.name' => ['required', 'string', 'max:255'],
            'form.email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'form.phone' => ['nullable', new BrazilianPhone],
            'form.role' => ['required', new Enum(Role::class)],
            'form.active' => ['boolean'],
            'form.password' => ['nullable', Password::defaults()],
        ], attributes: [
            'form.name' => 'nome', 'form.email' => 'e-mail', 'form.phone' => 'telefone',
            'form.role' => 'perfil', 'form.password' => 'senha',
        ]);

        $role = Role::from($this->form['role']);

        if ($user && $this->roleIsLocked($user) && $user->primaryRole() !== $role) {
            $this->addError('form.role', 'O perfil deste usuário acompanha o cadastro de executivo ou indicador. Altere por lá.');

            return;
        }

        $result = $action->handle($user, [
            'name' => $this->form['name'],
            'email' => $this->form['email'],
            'phone' => Phone::normalize($this->form['phone']),
            'active' => $this->form['active'],
            'password' => $this->form['password'] ?: null,
        ], $role, auth()->user());

        Flux::modal('user-form')->close();

        $message = $user ? 'Usuário atualizado.' : 'Usuário criado.';
        if ($result['reset_link_sent']) {
            $message .= ' Enviamos um link para ele criar a senha.';
        }
        Flux::toast(variant: 'success', text: $message);
        $this->resetForm();
    }

    public function toggleActive(int $id, SaveUser $action): void
    {
        $this->authorize('users.manage');
        $user = User::query()->findOrFail($id);
        $role = $user->primaryRole() ?? Role::Manager;

        if ($this->roleIsLocked($user)) {
            Flux::toast(variant: 'warning', text: 'Use o cadastro de executivos ou de indicadores para mudar o status deste acesso.');

            return;
        }

        try {
            $action->handle($user, [
                'name' => $user->name, 'email' => $user->email, 'phone' => $user->phone, 'active' => ! $user->active,
            ], $role, auth()->user());
        } catch (ValidationException $e) {
            Flux::toast(variant: 'danger', text: collect($e->errors())->flatten()->first());

            return;
        }

        Flux::toast(variant: 'success', text: $user->fresh()?->active ? 'Acesso reativado.' : 'Acesso desativado.');
    }

    private function roleIsLocked(User $user): bool
    {
        return $user->executive()->exists() || $user->regionalIndicator()->exists();
    }

    private function resetForm(): void
    {
        $this->resetValidation();
        $this->editingId = null;
        $this->form = ['name' => '', 'email' => '', 'phone' => '', 'role' => Role::Manager->value, 'active' => true, 'password' => ''];
    }

    /** @return LengthAwarePaginator<int, User> */
    #[Computed]
    public function users(): LengthAwarePaginator
    {
        return User::query()
            ->with('roles')
            ->withExists(['executive', 'regionalIndicator'])
            ->when($this->search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('email', 'like', "%{$this->search}%")))
            ->when($this->roleFilter !== '', fn ($q) => $q->role($this->roleFilter))
            ->when($this->statusFilter !== '', fn ($q) => $q->where('active', $this->statusFilter === 'ativo'))
            ->orderByDesc('active')
            ->orderBy('name')
            ->paginate(15);
    }

    public function render(): View
    {
        return view('livewire.admin.users', ['roles' => Role::cases()]);
    }
}
