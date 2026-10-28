<div class="space-y-6">
    <!-- Cabecera -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-black text-white flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-400"></span>
                Control Económico · Ingresos y Gastos Adicionales
            </h1>
            <p class="text-xs text-slate-400 mt-1">
                Registro de facturaciones de clientes y compras operativas de material (separadas del coste laboral).
            </p>
        </div>

        <button wire:click="abrirModalCrear"
            class="px-4 py-2 rounded-xl text-xs font-semibold bg-emerald-600 hover:bg-emerald-500 text-white shadow-md transition flex items-center gap-1.5 shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            Nuevo Movimiento
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
            <label class="block text-slate-400 mb-1 font-medium">Proyecto</label>
            <select wire:model.live="proyectoFiltro"
                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
                <option value="">Todos los proyectos</option>
                @foreach($proyectos as $p)
                    <option value="{{ $p->id }}">{{ $p->nombre }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-slate-400 mb-1 font-medium">Tipo</label>
            <select wire:model.live="tipoFiltro"
                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
                <option value="">Todos los tipos</option>
                <option value="ingreso">Solo Ingresos (Facturación)</option>
                <option value="gasto">Solo Gastos Adicionales (Materiales)</option>
            </select>
        </div>
    </div>

    <!-- Tabla de Movimientos -->
    <div class="bg-slate-900/80 rounded-2xl border border-slate-800 overflow-hidden shadow-xl text-xs">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="bg-slate-950/70 border-b border-slate-800 text-[11px] uppercase tracking-wider text-slate-400">
                    <tr>
                        <th class="px-5 py-3.5">Fecha</th>
                        <th class="px-5 py-3.5">Tipo</th>
                        <th class="px-5 py-3.5">Proyecto e Instalación</th>
                        <th class="px-5 py-3.5">Concepto</th>
                        <th class="px-5 py-3.5">Importe</th>
                        <th class="px-5 py-3.5 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60 text-slate-300">
                    @forelse($movimientos as $m)
                        <tr class="hover:bg-slate-800/40 transition">
                            <td class="px-5 py-3.5 font-medium text-white whitespace-nowrap">
                                {{ $m->fecha->format('d/m/Y') }}
                            </td>
                            <td class="px-5 py-3.5">
                                @if($m->tipo === 'ingreso')
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                        + Ingreso
                                    </span>
                                @else
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/10 text-amber-400 border border-amber-500/20">
                                        - Gasto Adicional
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5">
                                <span class="font-bold text-white block">{{ $m->proyecto->nombre }}</span>
                                <span class="text-[10px] text-slate-400">
                                    {{ $m->instalacion ? $m->instalacion->nombre : 'Proyecto general' }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 max-w-sm">
                                <span class="text-white font-medium block">{{ $m->concepto }}</span>
                                @if($m->observaciones)
                                    <span class="text-[10px] text-slate-400 block truncate">{{ $m->observaciones }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 whitespace-nowrap">
                                <span class="text-sm font-black {{ $m->tipo === 'ingreso' ? 'text-emerald-400' : 'text-amber-400' }}">
                                    {{ $m->tipo === 'ingreso' ? '+' : '-' }} {{ number_format($m->importe, 2, ',', '.') }} €
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-right whitespace-nowrap space-x-1">
                                <button wire:click="abrirModalEditar({{ $m->id }})"
                                    class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 font-medium text-[11px]">
                                    Editar
                                </button>
                                <button wire:click="confirmarEliminar({{ $m->id }})"
                                    class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-rose-900/40 text-rose-400 font-medium text-[11px]">
                                    Eliminar
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-8 text-center text-slate-400">
                                No se encontraron movimientos económicos registrados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($movimientos->hasPages())
            <div class="p-4 border-t border-slate-800">
                {{ $movimientos->links() }}
            </div>
        @endif
    </div>

    <!-- Modal Crear / Editar Movimiento -->
    @if($modalOpen)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6 shadow-2xl relative text-xs">
                <h3 class="text-base font-bold text-white mb-4">
                    {{ $movimientoId ? 'Editar Movimiento Económico' : 'Nuevo Movimiento Económico' }}
                </h3>

                <form wire:submit="guardar" class="space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Tipo de Movimiento *</label>
                            <select wire:model="tipo" required
                                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500 font-semibold">
                                <option value="ingreso">Ingreso (Facturación)</option>
                                <option value="gasto">Gasto Adicional (Materiales)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Fecha *</label>
                            <input type="date" wire:model="fecha" required
                                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
                        </div>
                    </div>

                    <div>
                        <label class="block text-slate-300 font-medium mb-1">Proyecto *</label>
                        <select wire:model.live="proyecto_id" required
                            class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
                            @foreach($proyectos as $p)
                                <option value="{{ $p->id }}">{{ $p->nombre }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-slate-300 font-medium mb-1">Instalación (Opcional)</label>
                        <select wire:model="instalacion_id"
                            class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
                            <option value="">Afecta al proyecto en general</option>
                            @foreach($instalacionesDelProyecto as $inst)
                                <option value="{{ $inst->id }}">{{ $inst->nombre }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-slate-300 font-medium mb-1">Concepto *</label>
                        <input type="text" wire:model="concepto" required placeholder="Ej: Certificación mensual mayo / Botiquín de socorrista"
                            class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
                    </div>

                    <div>
                        <label class="block text-slate-300 font-medium mb-1">Importe en Euros (€) *</label>
                        <input type="number" step="0.01" wire:model="importe" required
                            class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500 font-mono text-sm">
                    </div>

                    <div>
                        <label class="block text-slate-300 font-medium mb-1">Observaciones</label>
                        <textarea wire:model="observaciones" rows="2"
                            class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500"></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-800">
                        <button type="button" wire:click="$set('modalOpen', false)"
                            class="px-4 py-2 rounded-xl bg-slate-800 text-slate-300">Cancelar</button>
                        <button type="submit"
                            class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-semibold">Guardar Movimiento</button>
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
                        <h4 class="text-sm font-bold text-white">¿Eliminar movimiento económico?</h4>
                        <p class="text-[11px] text-slate-400">Esta acción eliminará el registro financiero del proyecto.</p>
                    </div>
                </div>

                <p class="text-slate-300 bg-slate-950/60 p-3 rounded-xl border border-slate-800 mb-4">
                    ¿Estás seguro de que deseas eliminar este movimiento económico de forma permanente?
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
