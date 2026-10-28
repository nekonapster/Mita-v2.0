<div class="space-y-6">
    <!-- Cabecera -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-black text-white flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-indigo-400"></span>
                Control Económico · Catálogo de Tarifas de Coste por Hora
            </h1>
            <p class="text-xs text-slate-400 mt-1">
                Tarifas por hora para cálculo de costes previstos y reales. Prioridad: tarifa de empleado > tarifa de categoría profesional.
            </p>
        </div>

        <button wire:click="abrirModalCrear"
            class="px-4 py-2 rounded-xl text-xs font-semibold bg-indigo-600 hover:bg-indigo-500 text-white shadow-md transition flex items-center gap-1.5 shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            Nueva Tarifa
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

    <!-- Filtros -->
    <div class="bg-slate-900/80 p-4 rounded-2xl border border-slate-800 grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
        <div>
            <label class="block text-slate-400 mb-1 font-medium">Filtrar por Ámbito</label>
            <select wire:model.live="tipoFiltro"
                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
                <option value="">Todas las tarifas</option>
                <option value="categoria">Tarifas generales por Categoría</option>
                <option value="empleado">Tarifas específicas por Empleado</option>
            </select>
        </div>
    </div>

    <!-- Tabla de Tarifas -->
    <div class="bg-slate-900/80 rounded-2xl border border-slate-800 overflow-hidden shadow-xl text-xs">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="bg-slate-950/70 border-b border-slate-800 text-[11px] uppercase tracking-wider text-slate-400">
                    <tr>
                        <th class="px-5 py-3.5">Ámbito de Aplicación</th>
                        <th class="px-5 py-3.5">Coste / Hora</th>
                        <th class="px-5 py-3.5">Vigencia</th>
                        <th class="px-5 py-3.5">Estado Vigencia</th>
                        <th class="px-5 py-3.5">Observaciones</th>
                        <th class="px-5 py-3.5 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60 text-slate-300">
                    @forelse($tarifas as $t)
                        @php
                            $vigente = $t->fecha_inicio <= now()->toDateString() && ($t->fecha_fin === null || $t->fecha_fin >= now()->toDateString());
                        @endphp
                        <tr class="hover:bg-slate-800/40 transition">
                            <td class="px-5 py-3.5 font-bold text-white">
                                @if($t->empleado)
                                    <div class="flex items-center gap-2">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-cyan-500/10 text-cyan-400 border border-cyan-500/20">
                                            Empleado
                                        </span>
                                        <span>{{ $t->empleado->nombre_completo }}</span>
                                    </div>
                                @elseif($t->categoria)
                                    <div class="flex items-center gap-2">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                            Categoría
                                        </span>
                                        <span>{{ $t->categoria->nombre }} ({{ $t->categoria->codigo }})</span>
                                    </div>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 whitespace-nowrap">
                                <span class="text-sm font-black text-white">
                                    {{ number_format($t->coste_hora, 2, ',', '.') }} €
                                </span>
                                <span class="text-[10px] text-slate-400">/ hora</span>
                            </td>
                            <td class="px-5 py-3.5 whitespace-nowrap">
                                <div>Desde {{ $t->fecha_inicio->format('d/m/Y') }}</div>
                                <div class="text-[10px] text-slate-400">
                                    {{ $t->fecha_fin ? 'Hasta ' . $t->fecha_fin->format('d/m/Y') : 'Indefinida' }}
                                </div>
                            </td>
                            <td class="px-5 py-3.5">
                                @if($vigente)
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                        Vigente hoy
                                    </span>
                                @else
                                    <span class="px-2 py-0.5 rounded text-[10px] font-medium bg-slate-800 text-slate-500">
                                        Histórica / Futura
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-slate-400 max-w-xs truncate">
                                {{ $t->observaciones ?: '—' }}
                            </td>
                            <td class="px-5 py-3.5 text-right whitespace-nowrap space-x-1">
                                <button wire:click="abrirModalEditar({{ $t->id }})"
                                    class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 font-medium text-[11px]">
                                    Editar
                                </button>
                                <button wire:click="confirmarEliminar({{ $t->id }})"
                                    class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-rose-900/40 text-rose-400 font-medium text-[11px]">
                                    Eliminar
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-8 text-center text-slate-400">
                                No se encontraron tarifas registradas.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($tarifas->hasPages())
            <div class="p-4 border-t border-slate-800">
                {{ $tarifas->links() }}
            </div>
        @endif
    </div>

    <!-- Modal Crear / Editar Tarifa -->
    @if($modalOpen)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6 shadow-2xl relative text-xs">
                <h3 class="text-base font-bold text-white mb-4">
                    {{ $tarifaId ? 'Editar Tarifa de Coste' : 'Nueva Tarifa de Coste' }}
                </h3>

                <form wire:submit="guardar" class="space-y-4">
                    <div>
                        <label class="block text-slate-300 font-medium mb-1">Ámbito de la Tarifa *</label>
                        <div class="grid grid-cols-2 gap-2">
                            <label class="p-2.5 rounded-xl border {{ $tipoAsignacion === 'categoria' ? 'border-cyan-500 bg-cyan-950/20 text-white' : 'border-slate-800 bg-slate-950 text-slate-400' }} flex items-center gap-2 cursor-pointer">
                                <input type="radio" value="categoria" wire:model.live="tipoAsignacion" class="text-cyan-600">
                                <span class="font-semibold">Por Categoría</span>
                            </label>
                            <label class="p-2.5 rounded-xl border {{ $tipoAsignacion === 'empleado' ? 'border-cyan-500 bg-cyan-950/20 text-white' : 'border-slate-800 bg-slate-950 text-slate-400' }} flex items-center gap-2 cursor-pointer">
                                <input type="radio" value="empleado" wire:model.live="tipoAsignacion" class="text-cyan-600">
                                <span class="font-semibold">Por Empleado</span>
                            </label>
                        </div>
                    </div>

                    @if($tipoAsignacion === 'categoria')
                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Categoría Profesional *</label>
                            <select wire:model="categoria_id" required
                                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
                                @foreach($categorias as $c)
                                    <option value="{{ $c->id }}">{{ $c->nombre }} ({{ $c->codigo }})</option>
                                @endforeach
                            </select>
                        </div>
                    @else
                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Empleado *</label>
                            <select wire:model="empleado_id" required
                                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
                                <option value="">Selecciona empleado...</option>
                                @foreach($empleados as $e)
                                    <option value="{{ $e->id }}">{{ $e->nombre_completo }} ({{ $e->categoria->nombre ?? '' }})</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div>
                        <label class="block text-slate-300 font-medium mb-1">Coste por Hora (€) *</label>
                        <input type="number" step="0.01" wire:model="coste_hora" required placeholder="15.50"
                            class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500 font-mono text-sm">
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Vigencia Desde *</label>
                            <input type="date" wire:model="fecha_inicio" required
                                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
                        </div>
                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Vigencia Hasta (Opcional)</label>
                            <input type="date" wire:model="fecha_fin"
                                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
                            @error('fecha_fin') <span class="text-rose-400 text-[10px]">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="block text-slate-300 font-medium mb-1">Observaciones</label>
                        <textarea wire:model="observaciones" rows="2" placeholder="Motivo o referencia salarial..."
                            class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500"></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-800">
                        <button type="button" wire:click="$set('modalOpen', false)"
                            class="px-4 py-2 rounded-xl bg-slate-800 text-slate-300">Cancelar</button>
                        <button type="submit"
                            class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold">Guardar Tarifa</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Modal Confirmar Eliminación -->
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
                        <h4 class="text-sm font-bold text-white">¿Eliminar tarifa de coste?</h4>
                        <p class="text-[11px] text-slate-400">Esta acción eliminará la tarifa seleccionada del catálogo.</p>
                    </div>
                </div>

                <p class="text-slate-300 bg-slate-950/60 p-3 rounded-xl border border-slate-800 mb-4">
                    ¿Estás seguro de que deseas eliminar permanentemente esta tarifa?
                </p>

                <div class="flex items-center justify-end gap-3">
                    <button type="button" wire:click="$set('modalDeleteOpen', false)"
                        class="px-4 py-2 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800 transition">
                        Cancelar
                    </button>
                    <button type="button" wire:click="ejecutarEliminar"
                        class="px-5 py-2 rounded-xl bg-rose-600 hover:bg-rose-500 text-white font-semibold shadow-lg shadow-rose-900/30 transition">
                        Confirmar Eliminación
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
