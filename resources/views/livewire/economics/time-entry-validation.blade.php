<div class="space-y-6">
    <!-- Cabecera -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-black text-white flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-indigo-400"></span>
                Control de Horas · Bandeja de Validación de Horas Realizadas
            </h1>
            <p class="text-xs text-slate-400 mt-1">
                Revisión de partes de trabajo, descansos no computables, desviaciones respecto a lo planificado y validación para costes.
            </p>
        </div>
    </div>

    <!-- Filtros -->
    <div class="bg-slate-900/80 p-4 rounded-2xl border border-slate-800 grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
        <div>
            <label class="block text-slate-400 mb-1 font-medium">Estado de Validación</label>
            <select wire:model.live="estadoFiltro"
                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
                <option value="">Todos los registros</option>
                <option value="pendiente_revision">⏳ Solo Pendientes de Revisión</option>
                <option value="validado">✓ Validados</option>
            </select>
        </div>
        <div>
            <label class="block text-slate-400 mb-1 font-medium">Fecha Turno Desde</label>
            <input type="date" wire:model.live="fechaDesde"
                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
        </div>
        <div>
            <label class="block text-slate-400 mb-1 font-medium">Fecha Turno Hasta</label>
            <input type="date" wire:model.live="fechaHasta"
                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
        </div>
    </div>

    <!-- Tabla de Registros -->
    <div class="bg-slate-900/80 rounded-2xl border border-slate-800 overflow-hidden shadow-xl text-xs">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="bg-slate-950/70 border-b border-slate-800 text-[11px] uppercase tracking-wider text-slate-400">
                    <tr>
                        <th class="px-5 py-3.5">Empleado</th>
                        <th class="px-5 py-3.5">Fecha y Turno Previsto</th>
                        <th class="px-5 py-3.5">Horas Reales e Intervalo</th>
                        <th class="px-5 py-3.5">Descanso</th>
                        <th class="px-5 py-3.5">Computable vs Previsto</th>
                        <th class="px-5 py-3.5">Estado</th>
                        <th class="px-5 py-3.5 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60 text-slate-300">
                    @forelse($registros as $reg)
                        @php
                            $turno = $reg->asignacion->turno;
                            $horasPrevistas = $turno->duracionHoras();
                            $horasReales = (float) $reg->horas_computables;
                            $desviacion = $horasReales - $horasPrevistas;
                        @endphp
                        <tr class="hover:bg-slate-800/40 transition">
                            <td class="px-5 py-3.5 font-bold text-white">
                                <div>{{ $reg->asignacion->empleado->nombre_completo }}</div>
                                <span class="text-[10px] text-slate-400 font-normal">
                                    {{ $reg->asignacion->empleado->categoria->nombre ?? '' }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5">
                                <span class="font-semibold text-white block">{{ $turno->fecha->format('d/m/Y') }}</span>
                                <span class="text-[11px] text-slate-300">{{ $turno->instalacion->nombre }}</span>
                                <span class="text-[10px] text-slate-400 block font-mono">
                                    Previsto: {{ Carbon\Carbon::parse($turno->hora_inicio)->format('H:i') }}-{{ Carbon\Carbon::parse($turno->hora_fin)->format('H:i') }} ({{ $horasPrevistas }}h)
                                </span>
                            </td>
                            <td class="px-5 py-3.5 whitespace-nowrap font-mono">
                                <div>{{ Carbon\Carbon::parse($reg->inicio_real)->format('H:i') }} - {{ Carbon\Carbon::parse($reg->fin_real)->format('H:i') }}</div>
                                @if($reg->observaciones)
                                    <span class="text-[10px] text-slate-400 font-sans block max-w-xs truncate">
                                        "{{ $reg->observaciones }}"
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 whitespace-nowrap">
                                {{ $reg->descanso_minutos }} min
                            </td>
                            <td class="px-5 py-3.5 whitespace-nowrap">
                                <span class="text-sm font-black text-white block">{{ $horasReales }} h</span>
                                @if($desviacion == 0)
                                    <span class="text-[10px] text-slate-400">Sin desviación</span>
                                @elseif($desviacion > 0)
                                    <span class="text-[10px] text-emerald-400 font-bold">+{{ round($desviacion, 2) }} h extras</span>
                                @else
                                    <span class="text-[10px] text-amber-400 font-bold">{{ round($desviacion, 2) }} h</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5">
                                @if($reg->estado === 'validado')
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 block text-center">
                                        ✓ Validado
                                    </span>
                                    <span class="text-[9px] text-slate-500 block text-center mt-0.5">
                                        {{ $reg->validador ? $reg->validador->name : '' }}
                                    </span>
                                @else
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-500/10 text-amber-400 border border-amber-500/20 block text-center">
                                        ⏳ Pendiente
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-right space-x-1 whitespace-nowrap">
                                <button wire:click="abrirModalEditar({{ $reg->id }})"
                                    class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 font-medium text-[11px]">
                                    Ajustar
                                </button>
                                @if($reg->estado === 'pendiente_revision')
                                    <button wire:click="validar({{ $reg->id }})"
                                        class="px-2.5 py-1 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-[11px]">
                                        Validar
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-8 text-center text-slate-400">
                                No hay registros de horas en la bandeja para los filtros seleccionados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($registros->hasPages())
            <div class="p-4 border-t border-slate-800">
                {{ $registros->links() }}
            </div>
        @endif
    </div>

    <!-- Modal Ajustar Horas -->
    @if($modalEditOpen)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6 shadow-2xl relative text-xs">
                <h3 class="text-base font-bold text-white mb-4">Ajustar Registro de Horas</h3>
                <form wire:submit="guardarEdicion" class="space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Inicio Real *</label>
                            <input type="datetime-local" wire:model="editInicioReal" required
                                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
                        </div>
                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Fin Real *</label>
                            <input type="datetime-local" wire:model="editFinReal" required
                                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
                        </div>
                    </div>

                    <div>
                        <label class="block text-slate-300 font-medium mb-1">Descanso No Computable (Minutos) *</label>
                        <input type="number" min="0" wire:model="editDescansoMinutos" required
                            class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
                    </div>

                    <div>
                        <label class="block text-slate-300 font-medium mb-1">Observaciones</label>
                        <textarea wire:model="editObservaciones" rows="2"
                            class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500"></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-800">
                        <button type="button" wire:click="$set('modalEditOpen', false)"
                            class="px-4 py-2 rounded-xl bg-slate-800 text-slate-300">Cancelar</button>
                        <button type="submit"
                            class="px-4 py-2 rounded-xl bg-cyan-600 hover:bg-cyan-500 text-white font-semibold">Guardar Ajuste</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
