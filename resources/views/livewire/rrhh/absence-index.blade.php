<div class="space-y-6">
    <!-- Cabecera -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-black text-white flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-amber-400"></span>
                Recursos Humanos · Ausencias, Vacaciones y Bajas
            </h1>
            <p class="text-xs text-slate-400 mt-1">
                Aprobación de solicitudes de personal y comprobación automática de incompatibilidad con turnos asignados.
            </p>
        </div>

        <button wire:click="abrirModalCrear"
            class="px-4 py-2 rounded-xl text-xs font-semibold bg-amber-600 hover:bg-amber-500 text-white shadow-md transition flex items-center gap-1.5 shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            Registrar Ausencia
        </button>
    </div>

    @if ($feedbackMessage)
        <div class="p-3 bg-emerald-500/10 border border-emerald-500/30 rounded-xl text-emerald-400 text-xs flex items-center justify-between">
            <span>{{ $feedbackMessage }}</span>
            <button wire:click="$set('feedbackMessage', null)" class="text-emerald-400 hover:text-emerald-300 font-bold ml-2">✕</button>
        </div>
    @endif

    @if ($feedbackError)
        <div class="p-3 bg-rose-500/10 border border-rose-500/30 rounded-xl text-rose-400 text-xs flex items-center justify-between">
            <span>{{ $feedbackError }}</span>
            <button wire:click="$set('feedbackError', null)" class="text-rose-400 hover:text-rose-300 font-bold ml-2">✕</button>
        </div>
    @endif

    @if (session()->has('warning'))
        <div class="p-3 bg-amber-500/10 border border-amber-500/30 rounded-xl text-amber-400 text-xs">
            {{ session('warning') }}
        </div>
    @endif

    @if (session()->has('success'))
        <div class="p-3 bg-emerald-500/10 border border-emerald-500/30 rounded-xl text-emerald-400 text-xs">
            {{ session('success') }}
        </div>
    @endif

    <!-- Barra de Filtros -->
    <div class="bg-slate-900/80 p-4 rounded-2xl border border-slate-800 grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
        <div>
            <label class="block text-slate-400 mb-1 font-medium">Estado</label>
            <select wire:model.live="estadoFiltro"
                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
                <option value="">Todos los estados</option>
                <option value="pendiente">Solo Pendientes de Aprobación</option>
                <option value="aprobada">Aprobadas</option>
                <option value="rechazada">Rechazadas</option>
            </select>
        </div>

        <div>
            <label class="block text-slate-400 mb-1 font-medium">Tipo de Ausencia</label>
            <select wire:model.live="tipoFiltro"
                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
                <option value="">Todos los tipos</option>
                <option value="vacaciones">Vacaciones</option>
                <option value="baja">Baja Médica</option>
                <option value="permiso">Permiso Retribuido</option>
                <option value="otro">Otro</option>
            </select>
        </div>

        <div>
            <label class="block text-slate-400 mb-1 font-medium">Empleado</label>
            <select wire:model.live="empleadoFiltro"
                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
                <option value="">Todos los empleados</option>
                @foreach($empleados as $emp)
                    <option value="{{ $emp->id }}">{{ $emp->nombre_completo }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <!-- Tabla de Ausencias -->
    <div class="bg-slate-900/80 rounded-2xl border border-slate-800 overflow-hidden shadow-xl text-xs">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="bg-slate-950/70 border-b border-slate-800 text-[11px] uppercase tracking-wider text-slate-400">
                    <tr>
                        <th class="px-5 py-3.5">Empleado</th>
                        <th class="px-5 py-3.5">Tipo</th>
                        <th class="px-5 py-3.5">Período (Inicio - Fin)</th>
                        <th class="px-5 py-3.5">Observaciones / Motivo</th>
                        <th class="px-5 py-3.5">Estado</th>
                        <th class="px-5 py-3.5 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60 text-slate-300">
                    @forelse($ausencias as $a)
                        <tr class="hover:bg-slate-800/40 transition">
                            <td class="px-5 py-3.5 font-semibold text-white">
                                <a href="{{ route('rrhh.empleados.show', $a->empleado_id) }}" class="hover:text-cyan-400">
                                    {{ $a->empleado->nombre_completo }}
                                </a>
                                <span class="block text-[10px] text-slate-400">{{ $a->empleado->categoria->nombre ?? '' }}</span>
                            </td>
                            <td class="px-5 py-3.5 capitalize font-medium">
                                {{ $a->tipo }}
                            </td>
                            <td class="px-5 py-3.5 whitespace-nowrap">
                                <div class="font-medium text-white">{{ $a->fecha_hora_inicio->format('d/m/Y H:i') }}</div>
                                <div class="text-[10px] text-slate-400">Hasta {{ $a->fecha_hora_fin->format('d/m/Y H:i') }}</div>
                            </td>
                            <td class="px-5 py-3.5 text-slate-400 max-w-xs truncate">
                                {{ $a->observaciones ?: '—' }}
                            </td>
                            <td class="px-5 py-3.5">
                                @if($a->estado === 'aprobada')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                        ✓ Aprobada
                                    </span>
                                @elseif($a->estado === 'pendiente')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/10 text-amber-400 border border-amber-500/20">
                                        ⏳ Pendiente
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-rose-500/10 text-rose-400 border border-rose-500/20">
                                        ✕ Rechazada
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-right space-x-1 whitespace-nowrap">
                                @if($a->estado === 'pendiente')
                                    <button wire:click="aprobar({{ $a->id }})"
                                        class="px-2.5 py-1 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-semibold transition text-[11px]">
                                        Aprobar
                                    </button>
                                    <button wire:click="rechazar({{ $a->id }})"
                                        class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-rose-400 font-semibold transition text-[11px]">
                                        Rechazar
                                    </button>
                                @else
                                    <span class="text-slate-500 text-[10px] mr-1">
                                        {{ $a->aprobador ? 'Por ' . $a->aprobador->name : 'Procesada' }}
                                    </span>
                                @endif
                                <button wire:click="eliminar({{ $a->id }})"
                                    wire:confirm="¿Seguro que deseas eliminar este registro de ausencia?"
                                    title="Eliminar ausencia"
                                    class="p-1 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/20 transition inline-flex items-center align-middle">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                    </svg>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-8 text-center text-slate-400">
                                No se encontraron registros de ausencia.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($ausencias->hasPages())
            <div class="p-4 border-t border-slate-800">
                {{ $ausencias->links() }}
            </div>
        @endif
    </div>

    <!-- Modal Registrar Ausencia -->
    @if($modalCrearOpen)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6 shadow-2xl relative text-xs">
                <h3 class="text-base font-bold text-white mb-4">Registrar Ausencia de Empleado</h3>
                <form wire:submit="registrarAusencia" class="space-y-4">
                    <div>
                        <label class="block text-slate-300 font-medium mb-1">Empleado *</label>
                        <select wire:model="empleado_id" required
                            class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
                            @foreach($empleados as $emp)
                                <option value="{{ $emp->id }}">{{ $emp->nombre_completo }} ({{ $emp->categoria->nombre ?? '' }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-slate-300 font-medium mb-1">Tipo de Ausencia *</label>
                        <select wire:model="tipo" required
                            class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
                            <option value="vacaciones">Vacaciones</option>
                            <option value="baja">Baja Médica / Incapacidad Temporal</option>
                            <option value="permiso">Permiso Retribuido / Deber Inexcusable</option>
                            <option value="otro">Otro Motivo Justificado</option>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Fecha / Hora Inicio *</label>
                            <input type="datetime-local" wire:model="fecha_hora_inicio" required
                                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
                        </div>
                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Fecha / Hora Fin *</label>
                            <input type="datetime-local" wire:model="fecha_hora_fin" required
                                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
                        </div>
                    </div>

                    <div>
                        <label class="block text-slate-300 font-medium mb-1">Motivo / Observaciones</label>
                        <textarea wire:model="observaciones" rows="2"
                            class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500"></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-800">
                        <button type="button" wire:click="$set('modalCrearOpen', false)"
                            class="px-4 py-2 rounded-xl bg-slate-800 text-slate-300">Cancelar</button>
                        <button type="submit"
                            class="px-4 py-2 rounded-xl bg-amber-600 hover:bg-amber-500 text-white font-semibold">Guardar y Comprobar Conflictos</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
