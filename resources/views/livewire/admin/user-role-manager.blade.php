<div class="space-y-6">
    <!-- Cabecera de Página -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-black text-white flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-rose-500"></span>
                Administración · Usuarios y Roles de Acceso
            </h1>
            <p class="text-xs text-slate-400 mt-1">
                Gestión de cuentas de acceso, asignación de roles del sistema y vinculación con la plantilla de
                empleados.
            </p>
        </div>

        <button wire:click="abrirModalCrear"
            class="px-4 py-2 rounded-xl text-xs font-semibold bg-rose-600 hover:bg-rose-500 text-white shadow-md shadow-rose-900/30 transition flex items-center gap-1.5 shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
            </svg>
            Nuevo Usuario
        </button>
    </div>

    <!-- Alerta de Error Específico -->
    @if ($feedbackError)
        <div
            class="p-3 bg-rose-500/15 border border-rose-500/30 text-rose-300 rounded-xl text-xs flex items-center justify-between">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-rose-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span>{{ $feedbackError }}</span>
            </div>
            <button wire:click="$set('feedbackError', null)" class="text-rose-400 hover:text-white">✕</button>
        </div>
    @endif

    <!-- Barra de Filtros -->
    <div class="bg-slate-900/80 p-4 rounded-2xl border border-slate-800 grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
        <div>
            <label class="block text-slate-400 mb-1 font-medium">Búsqueda de usuarios</label>
            <input type="text" wire:model.live.debounce.300ms="search"
                placeholder="Buscar por nombre o correo electrónico..."
                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white placeholder-slate-500 focus:border-rose-500 focus:ring-1 focus:ring-rose-500">
        </div>

        <div>
            <label class="block text-slate-400 mb-1 font-medium">Filtrar por rol asignado</label>
            <select wire:model.live="roleFiltro"
                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-rose-500 focus:ring-1 focus:ring-rose-500">
                <option value="">Todos los roles</option>
                @foreach ($allRoles as $role)
                    <option value="{{ $role->id }}">{{ $role->display_name ?? ucfirst($role->name) }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <!-- Tabla de Usuarios -->
    <div class="bg-slate-900/80 rounded-2xl border border-slate-800 overflow-hidden shadow-xl">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-300">
                <thead
                    class="bg-slate-950/70 border-b border-slate-800 text-[11px] uppercase tracking-wider text-slate-400">
                    <tr>
                        <th class="px-5 py-3.5 font-semibold">Usuario</th>
                        <th class="px-5 py-3.5 font-semibold">Roles Asignados</th>
                        <th class="px-5 py-3.5 font-semibold">Empleado Vinculado</th>
                        <th class="px-5 py-3.5 font-semibold">Seguridad 2FA</th>
                        <th class="px-5 py-3.5 font-semibold text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    @forelse($users as $user)
                        <tr wire:key="usuario-fila-{{ $user->id }}" class="hover:bg-slate-800/40 transition">
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="w-8 h-8 rounded-full bg-slate-800 border border-slate-700 flex items-center justify-center text-xs font-bold text-white shrink-0">
                                        {{ strtoupper(substr($user->name, 0, 2)) }}
                                    </div>
                                    <div>
                                        <div class="font-bold text-white text-sm flex items-center gap-2">
                                            {{ $user->name }}
                                            @if ($user->id === auth()->id())
                                                <span
                                                    class="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-cyan-500/20 text-cyan-400 border border-cyan-500/30">Tú</span>
                                            @endif
                                        </div>
                                        <div class="text-[11px] text-slate-400">{{ $user->email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3.5">
                                <div class="flex flex-wrap gap-1.5">
                                    @forelse($user->roles as $role)
                                        @php
                                            $colorClass = match ($role->name) {
                                                'admin' => 'bg-rose-500/15 text-rose-400 border-rose-500/30',
                                                'rrhh' => 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30',
                                                'planner' => 'bg-amber-500/15 text-amber-400 border-amber-500/30',
                                                'accountant' => 'bg-indigo-500/15 text-indigo-400 border-indigo-500/30',
                                                'employee' => 'bg-sky-500/15 text-sky-400 border-sky-500/30',
                                                default => 'bg-slate-700/40 text-slate-300 border-slate-600',
                                            };
                                        @endphp
                                        <span
                                            class="px-2 py-0.5 rounded-full text-[10px] font-semibold border {{ $colorClass }}">
                                            {{ $role->display_name ?? ucfirst($role->name) }}
                                        </span>
                                    @empty
                                        <span class="text-[10px] text-slate-500 italic">Sin roles asignados</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="px-5 py-3.5">
                                @if ($user->employee)
                                    <div class="flex items-center gap-2">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                                        <div>
                                            <span class="font-medium text-slate-200">{{ $user->employee->nombre }}
                                                {{ $user->employee->apellidos }}</span>
                                            <span
                                                class="text-[10px] text-slate-400 block">{{ $user->employee->categoria->nombre ?? 'Sin categoría' }}</span>
                                        </div>
                                    </div>
                                @else
                                    <span class="text-slate-500 text-[11px] italic">Usuario de oficina / Sin
                                        ficha</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5">
                                @if ($user->two_factor_confirmed_at)
                                    <span
                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z">
                                            </path>
                                        </svg>
                                        2FA Activo
                                    </span>
                                @else
                                    <span
                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-medium bg-slate-800 text-slate-400 border border-slate-700">
                                        No configurado
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-2">
                                    <button wire:click="abrirModalEditar({{ $user->id }})"
                                        class="px-2.5 py-1 rounded-lg text-[11px] font-medium bg-slate-800 hover:bg-slate-700 text-slate-200 transition border border-slate-700 hover:border-slate-600">
                                        Editar
                                    </button>
                                    @if ($user->id !== auth()->id())
                                        {{-- Bloquear / Desbloquear cuenta --}}
                                        
                                        <button 
                                        type="button" 
                                        wire:key="usuario-bloqueo-{{ $user->id }}" 
                                        wire:click="cambiarEstadoCuenta({{ $user->id }})"
                                        wire:confirm="¿Confirmas que deseas cambiar el acceso de este usuario?"
                                        class="px-2.5 py-1 rounded-lg text-[11px] font-medium transition
            {{ $user->activo
                ? 'bg-amber-600 hover:bg-amber-500 text-white'
                : 'bg-emerald-600 hover:bg-emerald-500 text-white' }}">
                                            {{ $user->activo ? 'Bloquear' : 'Desbloquear' }}
                                        </button>

                                        {{-- Eliminar usuario --}}
                                        <button wire:click="confirmarEliminar({{ $user->id }})"
                                            class="px-2 py-1 rounded-lg text-[11px] font-medium bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 transition border border-rose-500/20 hover:border-rose-500/40">
                                            Eliminar
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-10 text-center text-slate-500">
                                No se encontraron usuarios con los criterios de búsqueda seleccionados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($users->hasPages())
            <div class="p-4 border-t border-slate-800">
                {{ $users->links() }}
            </div>
        @endif
    </div>

    <!-- Modal Crear / Editar Usuario -->
    @if ($modalOpen)
        <div
            class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
            <div
                class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-lg overflow-hidden shadow-2xl animate-in fade-in zoom-in-95 duration-150">
                <div class="px-6 py-4 border-b border-slate-800 flex items-center justify-between">
                    <h3 class="text-sm font-bold text-white flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                        {{ $userId ? 'Modificar Usuario: ' . $name : 'Crear Nuevo Usuario' }}
                    </h3>
                    <button wire:click="$set('modalOpen', false)" class="text-slate-400 hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <form wire:submit.prevent="guardar" class="p-6 space-y-4 text-xs">
                    <div>
                        <label class="block text-slate-400 mb-1 font-semibold">Nombre Completo *</label>
                        <input type="text" wire:model="name" placeholder="Ej. Ana Fernández Torres"
                            class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white placeholder-slate-500 focus:border-rose-500 focus:ring-1 focus:ring-rose-500">
                        @error('name')
                            <span class="text-rose-400 text-[10px] mt-1 block">{{ $message }}</span>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-slate-400 mb-1 font-semibold">Correo Electrónico *</label>
                        <input type="email" wire:model="email" placeholder="usuario@mita.es"
                            class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white placeholder-slate-500 focus:border-rose-500 focus:ring-1 focus:ring-rose-500">
                        @error('email')
                            <span class="text-rose-400 text-[10px] mt-1 block">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-slate-400 mb-1 font-semibold">
                                Contraseña {{ $userId ? '(Opcional)' : '*' }}
                            </label>
                            <input type="password" wire:model="password"
                                placeholder="{{ $userId ? 'Dejar en blanco para conservar' : 'Mínimo 8 caracteres' }}"
                                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white placeholder-slate-500 focus:border-rose-500 focus:ring-1 focus:ring-rose-500">
                            @error('password')
                                <span class="text-rose-400 text-[10px] mt-1 block">{{ $message }}</span>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-slate-400 mb-1 font-semibold">
                                Confirmar Contraseña
                            </label>
                            <input type="password" wire:model="password_confirmation"
                                placeholder="Repite la contraseña"
                                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white placeholder-slate-500 focus:border-rose-500 focus:ring-1 focus:ring-rose-500">
                        </div>
                    </div>

                    <div>
                        <label class="block text-slate-400 mb-2 font-semibold">Roles del Usuario (Múltiple selección
                            permitida) *</label>
                        <div
                            class="grid grid-cols-1 sm:grid-cols-2 gap-2 bg-slate-950/60 p-3 rounded-xl border border-slate-800">
                            @foreach ($allRoles as $role)
                                <label
                                    class="flex items-start gap-2.5 p-2 rounded-lg hover:bg-slate-900 cursor-pointer transition">
                                    <input type="checkbox" wire:model="selectedRoles" value="{{ $role->id }}"
                                        class="mt-0.5 rounded border-slate-700 text-rose-600 focus:ring-rose-500 bg-slate-900">
                                    <div>
                                        <span
                                            class="font-bold text-slate-200 block text-xs">{{ $role->display_name ?? ucfirst($role->name) }}</span>
                                        <span
                                            class="text-[10px] text-slate-400 leading-tight block">{{ $role->description }}</span>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                        @error('selectedRoles')
                            <span class="text-rose-400 text-[10px] mt-1 block">{{ $message }}</span>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-slate-400 mb-1 font-semibold">Vincular con Ficha de Empleado
                            (Opcional)</label>
                        <select wire:model="employee_id"
                            class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-rose-500 focus:ring-1 focus:ring-rose-500">
                            <option value="">-- Sin ficha de empleado (solo usuario de gestión) --</option>
                            @foreach ($allEmployees as $emp)
                                <option value="{{ $emp->id }}">
                                    {{ $emp->apellidos }}, {{ $emp->nombre }} [{{ $emp->dni_nie ?? 'Sin DNI' }}] -
                                    {{ $emp->categoria->nombre ?? '' }}
                                </option>
                            @endforeach
                        </select>
                        <span class="text-[10px] text-slate-400 mt-1 block">
                            Permite al usuario acceder al "Portal del Empleado" para consultar sus turnos asignados e
                            imputar horas de trabajo.
                        </span>
                        @error('employee_id')
                            <span class="text-rose-400 text-[10px] mt-1 block">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-800">
                        <button type="button" wire:click="$set('modalOpen', false)"
                            class="px-4 py-2 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800 transition">
                            Cancelar
                        </button>
                        <button type="submit"
                            class="px-5 py-2 rounded-xl bg-rose-600 hover:bg-rose-500 text-white font-semibold shadow-lg shadow-rose-900/30 transition">
                            {{ $userId ? 'Guardar Cambios' : 'Crear Usuario' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Modal Confirmar Eliminación -->
    @if ($modalDeleteOpen)
        <div
            class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
            <div
                class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-md overflow-hidden shadow-2xl p-6 text-xs">
                <div class="flex items-center gap-3 text-rose-400 mb-3">
                    <div
                        class="w-10 h-10 rounded-xl bg-rose-500/10 border border-rose-500/20 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z">
                            </path>
                        </svg>
                    </div>
                    <div>
                        <h4 class="text-sm font-bold text-white">¿Eliminar usuario?</h4>
                        <p class="text-[11px] text-slate-400">Esta acción desvinculará sus roles y eliminará su cuenta
                            de acceso.</p>
                    </div>
                </div>

                <p class="text-slate-300 bg-slate-950/60 p-3 rounded-xl border border-slate-800 mb-4">
                    ¿Estás seguro de que deseas eliminar la cuenta de <strong
                        class="text-white">{{ $userToDeleteName }}</strong>?
                </p>

                <div class="flex items-center justify-end gap-3">
                    <button type="button" wire:click="$set('modalDeleteOpen', false)"
                        class="px-4 py-2 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800 transition">
                        Cancelar
                    </button>
                    <button type="button" wire:click="eliminar"
                        class="px-5 py-2 rounded-xl bg-rose-600 hover:bg-rose-500 text-white font-semibold shadow-lg shadow-rose-900/30 transition">
                        Confirmar Eliminación
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
