<div class="space-y-6">
    <!-- Cabecera -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-black text-white flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-amber-400"></span>
                Planificación · Gestión y Publicación de Turnos
            </h1>
            <p class="text-xs text-slate-400 mt-1">
                Control de estados de turnos (borrador, publicado, cancelado) y publicación por lotes para el cuadrante.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <button wire:click="publicarBorradores"
                class="px-4 py-2 rounded-xl text-xs font-semibold bg-emerald-600 hover:bg-emerald-500 text-white shadow-md transition flex items-center gap-1.5 shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                Publicar Borradores del Período
            </button>
        </div>
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

    <!-- Filtros -->
    <div class="bg-slate-900/80 p-4 rounded-2xl border border-slate-800 grid grid-cols-1 sm:grid-cols-4 gap-3 text-xs">
        <div>
            <label class="block text-slate-400 mb-1 font-medium">Desde</label>
            <input type="date" wire:model.live="fechaDesde"
                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
        </div>
        <div>
            <label class="block text-slate-400 mb-1 font-medium">Hasta</label>
            <input type="date" wire:model.live="fechaHasta"
                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
        </div>
        <div>
            <label class="block text-slate-400 mb-1 font-medium">Estado</label>
            <select wire:model.live="estadoFiltro"
                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
                <option value="">Todos los estados</option>
                <option value="borrador">Borrador</option>
                <option value="publicado">Publicado</option>
                <option value="cancelado">Cancelado</option>
            </select>
        </div>
        <div>
            <label class="block text-slate-400 mb-1 font-medium">Proyecto</label>
            <select wire:model.live="proyectoFiltro"
                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
                <option value="">Todos los proyectos</option>
                @foreach($proyectos as $p)
                    <option value="{{ $p->id }}">{{ $p->nombre }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <!-- Tabla de Turnos -->
    <div class="bg-slate-900/80 rounded-2xl border border-slate-800 overflow-hidden shadow-xl text-xs">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="bg-slate-950/70 border-b border-slate-800 text-[11px] uppercase tracking-wider text-slate-400">
                    <tr>
                        <th class="px-5 py-3.5">Fecha</th>
                        <th class="px-5 py-3.5">Horario</th>
                        <th class="px-5 py-3.5">Instalación y Servicio</th>
                        <th class="px-5 py-3.5">Plazas y Asignados</th>
                        <th class="px-5 py-3.5">Estado</th>
                        <th class="px-5 py-3.5 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60 text-slate-300">
                    @forelse($turnos as $t)
                        <tr class="hover:bg-slate-800/40 transition">
                            <td class="px-5 py-3.5 font-bold text-white whitespace-nowrap">
                                {{ $t->fecha->format('d/m/Y') }}
                            </td>
                            <td class="px-5 py-3.5 whitespace-nowrap font-mono text-[11px]">
                                {{ Carbon\Carbon::parse($t->hora_inicio)->format('H:i') }} - {{ Carbon\Carbon::parse($t->hora_fin)->format('H:i') }}
                                <span class="text-[10px] text-slate-400">({{ $t->duracionHoras() }} h)</span>
                            </td>
                            <td class="px-5 py-3.5">
                                <span class="font-semibold text-slate-200 block">{{ $t->instalacion->nombre }}</span>
                                <span class="text-[10px] text-slate-400">{{ $t->actividad->nombre }} · {{ $t->categoria->nombre }}</span>
                            </td>
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-1.5 mb-1">
                                    <span class="font-bold text-white">{{ $t->plazasAsignadas() }} / {{ $t->plazas_necesarias }}</span>
                                    <span class="text-[10px] px-1.5 py-0.2 rounded
                                        @if($t->estadoCobertura() === 'cubierto') bg-emerald-500/20 text-emerald-400
                                        @elseif($t->estadoCobertura() === 'parcialmente_cubierto') bg-amber-500/20 text-amber-400
                                        @elseif($t->estadoCobertura() === 'con_conflicto') bg-purple-500/20 text-purple-400
                                        @else bg-rose-500/20 text-rose-400 @endif">
                                        {{ ucfirst(str_replace('_', ' ', $t->estadoCobertura())) }}
                                    </span>
                                </div>
                                <div class="text-[10px] text-slate-400">
                                    @foreach($t->asignacionesActivas as $a)
                                        <span class="block truncate">• {{ $a->empleado->nombre_completo }}</span>
                                    @endforeach
                                </div>
                            </td>
                            <td class="px-5 py-3.5">
                                @if($t->estado === 'publicado')
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                        Publicado
                                    </span>
                                @elseif($t->estado === 'borrador')
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-700 text-slate-300">
                                        Borrador
                                    </span>
                                @else
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-500/10 text-rose-400 border border-rose-500/20">
                                        Cancelado
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-right whitespace-nowrap space-x-1">
                                @if($t->estado === 'borrador')
                                    <button wire:click="cambiarEstado({{ $t->id }}, 'publicado')"
                                        class="px-2.5 py-1 rounded bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-[10px]">
                                        Publicar
                                    </button>
                                @elseif($t->estado === 'publicado')
                                    <button wire:click="cambiarEstado({{ $t->id }}, 'cancelado')"
                                        class="px-2 py-1 rounded bg-slate-800 hover:bg-rose-900/30 text-rose-400 text-[10px]">
                                        Cancelar
                                    </button>
                                @else
                                    <button wire:click="cambiarEstado({{ $t->id }}, 'publicado')"
                                        class="px-2 py-1 rounded bg-slate-800 hover:bg-slate-700 text-cyan-400 text-[10px]">
                                        Restaurar
                                    </button>
                                @endif
                                <button wire:click="confirmarEliminar({{ $t->id }})"
                                    title="Eliminar turno"
                                    class="p-1 rounded bg-slate-800 hover:bg-rose-900/40 text-rose-400 border border-rose-500/20 text-[10px] inline-flex items-center align-middle">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                    </svg>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-8 text-center text-slate-400">
                                No se encontraron turnos en el rango seleccionado.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($turnos->hasPages())
            <div class="p-4 border-t border-slate-800">
                {{ $turnos->links() }}
            </div>
        @endif
    </div>

    <!-- Modal Confirmar Eliminación de Turno -->
    @if($modalDeleteOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm animate-fade-in text-xs">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 max-w-md w-full shadow-2xl space-y-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-rose-500/10 text-rose-400 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-white">¿Eliminar Turno?</h3>
                        <p class="text-slate-400 text-[11px]">{{ $turnoToDeleteInfo }}</p>
                    </div>
                </div>

                @if($turnoTieneAsignaciones)
                    <div class="p-3 bg-amber-500/10 border border-amber-500/30 rounded-xl text-amber-300 text-[11px]">
                        ⚠️ Este turno tiene empleados asignados con horas sin validar. Al eliminar el turno, se cancelarán y retirarán estas asignaciones.
                    </div>
                @else
                    <p class="text-slate-300 text-xs">
                        Esta acción eliminará el turno por completo de la planificación.
                    </p>
                @endif

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-800">
                    <button type="button" wire:click="$set('modalDeleteOpen', false)"
                        class="px-4 py-2 rounded-xl text-slate-300 hover:bg-slate-800 font-semibold transition">
                        Cancelar
                    </button>
                    <button type="button" wire:click="ejecutarEliminar"
                        class="px-4 py-2 rounded-xl bg-rose-600 hover:bg-rose-500 text-white font-semibold shadow-lg shadow-rose-900/40 transition">
                        Sí, Eliminar Turno
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
