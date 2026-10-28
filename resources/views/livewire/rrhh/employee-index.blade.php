<div class="space-y-6">
    <!-- Cabecera de Página -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-black text-white flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-400"></span>
                Recursos Humanos · Plantilla de Empleados
            </h1>
            <p class="text-xs text-slate-400 mt-1">
                Fichas de empleados, categorías profesionales, estado de actividad y contratos vinculados.
            </p>
        </div>

        <button wire:click="abrirModalCrear"
            class="px-4 py-2 rounded-xl text-xs font-semibold bg-emerald-600 hover:bg-emerald-500 text-white shadow-md transition flex items-center gap-1.5 shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            Nuevo Empleado
        </button>
    </div>

    @if($feedbackMessage)
        <div class="p-3 bg-emerald-500/15 border border-emerald-500/30 text-emerald-300 rounded-xl text-xs flex items-center justify-between">
            <span>✓ {{ $feedbackMessage }}</span>
            <button wire:click="$set('feedbackMessage', null)" class="text-emerald-400 hover:text-white">✕</button>
        </div>
    @endif

    @if($feedbackError)
        <div class="p-3 bg-rose-500/15 border border-rose-500/30 text-rose-300 rounded-xl text-xs flex items-center justify-between">
            <span>⚠ {{ $feedbackError }}</span>
            <button wire:click="$set('feedbackError', null)" class="text-rose-400 hover:text-white">✕</button>
        </div>
    @endif

    <!-- Barra de Filtros -->
    <div class="bg-slate-900/80 p-4 rounded-2xl border border-slate-800 grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
        <div>
            <label class="block text-slate-400 mb-1 font-medium">Búsqueda rápida</label>
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Buscar por nombre, DNI o email..."
                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white placeholder-slate-500 focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500">
        </div>

        <div>
            <label class="block text-slate-400 mb-1 font-medium">Categoría profesional</label>
            <select wire:model.live="categoriaFiltro"
                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500">
                <option value="">Todas las categorías</option>
                @foreach($categorias as $cat)
                    <option value="{{ $cat->id }}">{{ $cat->nombre }} ({{ $cat->codigo }})</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-slate-400 mb-1 font-medium">Estado</label>
            <select wire:model.live="estadoFiltro"
                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500">
                <option value="">Todos los estados</option>
                <option value="1">Solo Activos</option>
                <option value="0">Inactivos</option>
            </select>
        </div>
    </div>

    <!-- Listado de Empleados -->
    <div class="bg-slate-900/80 rounded-2xl border border-slate-800 overflow-hidden shadow-xl">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-300">
                <thead class="bg-slate-950/70 border-b border-slate-800 text-[11px] uppercase tracking-wider text-slate-400">
                    <tr>
                        <th class="px-5 py-3.5 font-semibold">Empleado</th>
                        <th class="px-5 py-3.5 font-semibold">DNI / NIE</th>
                        <th class="px-5 py-3.5 font-semibold">Categoría</th>
                        <th class="px-5 py-3.5 font-semibold">Contacto</th>
                        <th class="px-5 py-3.5 font-semibold">Contrato Actual</th>
                        <th class="px-5 py-3.5 font-semibold">Estado</th>
                        <th class="px-5 py-3.5 font-semibold text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    @forelse($empleados as $emp)
                        <tr class="hover:bg-slate-800/40 transition">
                            <td class="px-5 py-3.5 font-semibold text-white">
                                <a href="{{ route('rrhh.empleados.show', $emp->id) }}" class="hover:text-cyan-400 transition flex items-center gap-2">
                                    <div class="w-7 h-7 rounded-full bg-slate-800 border border-slate-700 flex items-center justify-center font-bold text-[10px] text-cyan-300">
                                        {{ strtoupper(substr($emp->nombre, 0, 1) . substr($emp->apellidos, 0, 1)) }}
                                    </div>
                                    <span>{{ $emp->nombre_completo }}</span>
                                </a>
                            </td>
                            <td class="px-5 py-3.5 text-slate-400 font-mono text-[11px]">
                                {{ $emp->dni_nie ?: '—' }}
                            </td>
                            <td class="px-5 py-3.5">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-cyan-500/10 text-cyan-300 border border-cyan-500/20">
                                    {{ $emp->categoria->nombre ?? 'Sin categoría' }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-[11px]">
                                <div class="text-slate-300">{{ $emp->email ?: '—' }}</div>
                                <div class="text-slate-400">{{ $emp->telefono ?: '' }}</div>
                            </td>
                            <td class="px-5 py-3.5">
                                @php $contratoActual = $emp->contratos->first(); @endphp
                                @if($contratoActual)
                                    <span class="text-emerald-400 text-[11px] font-medium block">
                                        {{ $contratoActual->tipo_contrato }}
                                    </span>
                                    <span class="text-[10px] text-slate-400">
                                        {{ $contratoActual->jornada_semanal_horas }} h/sem (Desde {{ $contratoActual->fecha_inicio->format('d/m/Y') }})
                                    </span>
                                @else
                                    <span class="text-rose-400 text-[11px] font-semibold">Sin contrato registrado</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5">
                                @if($emp->activo)
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span> Activo
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-700/50 text-slate-400 border border-slate-600/50">
                                        Inactivo
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-right space-x-1 whitespace-nowrap">
                                <a href="{{ route('rrhh.empleados.show', $emp->id) }}"
                                    class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-cyan-400 font-medium transition inline-block text-[11px]">
                                    Ver Ficha
                                </a>
                                <button wire:click="abrirModalEditar({{ $emp->id }})"
                                    class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 font-medium transition text-[11px]">
                                    Editar
                                </button>
                                <button wire:click="confirmarEliminar({{ $emp->id }})"
                                    class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-rose-900/40 text-rose-400 font-medium transition text-[11px]">
                                    Eliminar
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-8 text-center text-slate-400">
                                No se encontraron empleados con los criterios de búsqueda seleccionados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($empleados->hasPages())
            <div class="p-4 border-t border-slate-800">
                {{ $empleados->links() }}
            </div>
        @endif
    </div>

    <!-- Modal Crear / Editar Empleado -->
    @if($modalOpen)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-lg w-full p-6 shadow-2xl relative text-xs">
                <h3 class="text-base font-bold text-white mb-4">
                    {{ $empleadoId ? 'Editar Empleado' : 'Nuevo Empleado' }}
                </h3>

                <form wire:submit="guardar" class="space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Nombre *</label>
                            <input type="text" wire:model="nombre" required
                                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
                            @error('nombre') <span class="text-rose-400 text-[10px]">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Apellidos *</label>
                            <input type="text" wire:model="apellidos" required
                                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
                            @error('apellidos') <span class="text-rose-400 text-[10px]">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-slate-300 font-medium mb-1">DNI / NIE</label>
                            <input type="text" wire:model="dni_nie" placeholder="12345678Z"
                                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
                            @error('dni_nie') <span class="text-rose-400 text-[10px]">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Categoría Profesional *</label>
                            <select wire:model="categoria_id" required
                                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
                                @foreach($categorias as $cat)
                                    <option value="{{ $cat->id }}">{{ $cat->nombre }} ({{ $cat->codigo }})</option>
                                @endforeach
                            </select>
                            @error('categoria_id') <span class="text-rose-400 text-[10px]">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Correo Electrónico</label>
                            <input type="email" wire:model="email"
                                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
                            @error('email') <span class="text-rose-400 text-[10px]">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Teléfono</label>
                            <input type="text" wire:model="telefono"
                                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
                            @error('telefono') <span class="text-rose-400 text-[10px]">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="block text-slate-300 font-medium mb-1">Observaciones</label>
                        <textarea wire:model="observaciones" rows="2"
                            class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500"></textarea>
                    </div>

                    <div class="flex items-center gap-2">
                        <input type="checkbox" id="empActivo" wire:model="activo"
                            class="rounded border-slate-700 bg-slate-950 text-cyan-600 focus:ring-cyan-500">
                        <label for="empActivo" class="text-slate-300 font-medium">Empleado en estado activo</label>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-800">
                        <button type="button" wire:click="$set('modalOpen', false)"
                            class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300">
                            Cancelar
                        </button>
                        <button type="submit"
                            class="px-4 py-2 rounded-xl bg-cyan-600 hover:bg-cyan-500 text-white font-semibold">
                            Guardar Empleado
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Modal Confirmar Eliminación / Baja -->
    @if($modalDeleteOpen)
        <div class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-md overflow-hidden shadow-2xl p-6 text-xs">
                <div class="flex items-center gap-3 text-rose-400 mb-3">
                    <div class="w-10 h-10 rounded-xl bg-rose-500/10 border border-rose-500/20 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                    </div>
                    <div>
                        <h4 class="text-sm font-bold text-white">¿Eliminar ficha de empleado?</h4>
                        <p class="text-[11px] text-slate-400">Gestión de personal y archivo laboral</p>
                    </div>
                </div>

                <div class="bg-slate-950/60 p-3.5 rounded-xl border border-slate-800 mb-4 space-y-2">
                    <p class="text-slate-300">
                        Empleado seleccionado: <strong class="text-white">{{ $empleadoToDeleteNombre }}</strong>
                    </p>
                    @if($tieneHistorialLaboral)
                        <div class="p-2.5 rounded-lg bg-amber-500/10 border border-amber-500/20 text-amber-300 text-[11px]">
                            ⚠ Este empleado tiene contratos, turnos o ausencias vinculadas. Por normativa de integridad laboral y prevención de pérdida de datos, <strong>se marcará como Inactivo (Baja)</strong> en lugar de purgar su histórico.
                        </div>
                    @else
                        <p class="text-slate-400 text-[11px]">
                            Este empleado no tiene contratos ni turnos asociados. Se eliminará completamente del sistema.
                        </p>
                    @endif
                </div>

                <div class="flex items-center justify-end gap-3">
                    <button type="button" wire:click="$set('modalDeleteOpen', false)"
                        class="px-4 py-2 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800 transition">
                        Cancelar
                    </button>
                    <button type="button" wire:click="eliminar"
                        class="px-5 py-2 rounded-xl bg-rose-600 hover:bg-rose-500 text-white font-semibold shadow-lg shadow-rose-900/30 transition">
                        {{ $tieneHistorialLaboral ? 'Dar de Baja (Desactivar)' : 'Eliminar Registro' }}
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
