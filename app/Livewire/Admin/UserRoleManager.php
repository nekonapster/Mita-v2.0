<?php

namespace App\Livewire\Admin;

use App\Models\Empleado;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;
use Livewire\WithPagination;

class UserRoleManager extends Component
{
    use WithPagination;

    public string $search = '';
    public string $roleFiltro = '';

    public bool $modalOpen = false;
    public bool $modalDeleteOpen = false;
    public ?int $userId = null;
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';
    public array $selectedRoles = [];
    public ?int $employee_id = null;
    public ?int $userToDeleteId = null;
    public string $userToDeleteName = '';
    public ?string $feedbackError = null;

    public function mount(): void
    {
        abort_unless(auth()->check() && auth()->user()->isAdmin(), 403, 'Acceso restringido a administradores.');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingRoleFiltro(): void
    {
        $this->resetPage();
    }

    public function abrirModalCrear(): void
    {
        $this->reset(['userId', 'name', 'email', 'password', 'password_confirmation', 'selectedRoles', 'employee_id']);
        $this->resetValidation();
        // Por defecto seleccionar el rol de empleado si existe
        $defaultRole = Role::where('name', Role::EMPLOYEE)->first();
        if ($defaultRole) {
            $this->selectedRoles = [$defaultRole->id];
        }
        $this->modalOpen = true;
    }

    public function abrirModalEditar(int $id): void
    {
        $this->resetValidation();
        $user = User::with('roles')->findOrFail($id);
        $this->userId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->password = '';
        $this->password_confirmation = '';
        $this->selectedRoles = $user->roles->pluck('id')->toArray();
        $this->employee_id = $user->employee_id;
        $this->modalOpen = true;
    }

    public function guardar(): void
    {
        $rules = [
            'name' => 'required|string|max:150',
            'email' => 'required|email|max:150|unique:users,email,' . $this->userId,
            'selectedRoles' => 'required|array|min:1',
            'selectedRoles.*' => 'exists:roles,id',
            'employee_id' => 'nullable|exists:empleados,id',
        ];

        if ($this->userId) {
            $rules['password'] = 'nullable|string|min:8|same:password_confirmation';
        } else {
            $rules['password'] = 'required|string|min:8|same:password_confirmation';
        }

        $this->validate($rules, [
            'name.required' => 'El nombre es obligatorio.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'El formato del correo electrónico no es válido.',
            'email.unique' => 'Este correo ya está registrado en el sistema.',
            'password.required' => 'La contraseña es obligatoria para nuevos usuarios.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.same' => 'Las contraseñas no coinciden.',
            'selectedRoles.required' => 'Debe asignar al menos un rol al usuario.',
            'selectedRoles.min' => 'Debe asignar al menos un rol al usuario.',
        ]);

        if ($this->userId) {
            $user = User::findOrFail($this->userId);
            $user->name = $this->name;
            $user->email = $this->email;
            $user->employee_id = $this->employee_id ?: null;

            if (!empty($this->password)) {
                $user->password = Hash::make($this->password);
            }

            $user->save();
            $user->roles()->sync($this->selectedRoles);

            session()->flash('success', "Usuario '{$user->name}' actualizado correctamente.");
        } else {
            $user = User::create([
                'name' => $this->name,
                'email' => $this->email,
                'password' => Hash::make($this->password),
                'employee_id' => $this->employee_id ?: null,
            ]);

            $user->roles()->sync($this->selectedRoles);

            session()->flash('success', "Usuario '{$user->name}' creado con éxito.");
        }

        $this->modalOpen = false;
        $this->reset(['userId', 'name', 'email', 'password', 'password_confirmation', 'selectedRoles', 'employee_id']);
    }

    public function confirmarEliminar(int $id): void
    {
        $this->feedbackError = null;

        if ($id === auth()->id()) {
            $this->feedbackError = 'No puede eliminar su propia cuenta de usuario conectada.';
            session()->flash('error', $this->feedbackError);
            return;
        }

        $user = User::with('roles')->findOrFail($id);

        // Prevenir eliminar si es el único administrador
        if ($user->isAdmin()) {
            $adminCount = User::whereHas('roles', fn ($q) => $q->where('name', Role::ADMIN))->count();
            if ($adminCount <= 1) {
                $this->feedbackError = 'No se puede eliminar el único administrador existente del sistema.';
                session()->flash('error', $this->feedbackError);
                return;
            }
        }

        $this->userToDeleteId = $user->id;
        $this->userToDeleteName = $user->name;
        $this->modalDeleteOpen = true;
    }

    public function eliminar(): void
    {
        if (!$this->userToDeleteId) {
            return;
        }

        try {
            $user = User::findOrFail($this->userToDeleteId);
            $userName = $user->name;
            
            $user->roles()->detach();
            $user->delete();

            $this->modalDeleteOpen = false;
            $this->reset(['userToDeleteId', 'userToDeleteName']);
            session()->flash('success', "Usuario '{$userName}' eliminado correctamente.");
        } catch (\Throwable $e) {
            $this->modalDeleteOpen = false;
            $this->feedbackError = 'No se puede eliminar este usuario porque cuenta con incidencias, aprobaciones o registros históricos vinculados.';
            session()->flash('error', $this->feedbackError);
        }
    }

    public function render()
    {
        $query = User::with(['roles', 'employee'])
            ->when($this->search, function ($q) {
                $q->where(function ($sub) {
                    $sub->where('name', 'ilike', "%{$this->search}%")
                        ->orWhere('email', 'ilike', "%{$this->search}%");
                });
            })
            ->when($this->roleFiltro !== '', function ($q) {
                $q->whereHas('roles', fn ($r) => $r->where('roles.id', $this->roleFiltro));
            })
            ->orderBy('name');

        return view('livewire.admin.user-role-manager', [
            'users' => $query->paginate(10),
            'allRoles' => Role::orderBy('name')->get(),
            'allEmployees' => Empleado::where('activo', true)->orderBy('apellidos')->orderBy('nombre')->get(),
        ])->layout('layouts.app', ['title' => 'Gestión de Usuarios y Roles']);
    }
}
