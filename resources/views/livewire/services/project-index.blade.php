<div class="space-y-6">
    <!-- Cabecera -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-black text-white flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-cyan-400"></span>
                Servicios · Proyectos de Servicio
            </h1>
            <p class="text-xs text-slate-400 mt-1">
                Campañas de socorrismo, escuelas de natación y contratos de gestión de instalaciones deportivas.
            </p>
        </div>

        <button wire:click="abrirModalCrear"
            class="px-4 py-2 rounded-xl text-xs font-semibold bg-cyan-600 hover:bg-cyan-500 text-white shadow-md transition flex items-center gap-1.5 shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            Nuevo Proyecto
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
    <div class="bg-slate-900/80 p-4 rounded-2xl border border-slate-800 grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
        <div>
            <label class="block text-slate-400 mb-1 font-medium">Buscar proyecto</label>
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Nombre del proyecto..."
                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
        </div>
        <div>
            <label class="block text-slate-400 mb-1 font-medium">Estado</label>
            <select wire:model.live="estadoFiltro"
                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
                <option value="">Todos los estados</option>
                <option value="activo">Activos</option>
                <option value="borrador">Borradores</option>
                <option value="finalizado">Finalizados</option>
                <option value="cancelado">Cancelados</option>
            </select>
        </div>
        <div>
            <label class="block text-slate-400 mb-1 font-medium">Cliente</label>
            <select wire:model.live="clienteFiltro"
                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
                <option value="">Todos los clientes</option>
                @foreach($clientes as $cli)
                    <option value="{{ $cli->id }}">{{ $cli->razon_social }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <!-- Tabla de Proyectos -->
    <div class="bg-slate-900/80 rounded-2xl border border-slate-800 overflow-hidden shadow-xl text-xs">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="bg-slate-950/70 border-b border-slate-800 text-[11px] uppercase tracking-wider text-slate-400">
                    <tr>
                        <th class="px-5 py-3.5">Proyecto</th>
                        <th class="px-5 py-3.5">Cliente</th>
                        <th class="px-5 py-3.5">Instalaciones</th>
                        <th class="px-5 py-3.5">Período</th>
                        <th class="px-5 py-3.5">Necesidades</th>
                        <th class="px-5 py-3.5">Estado</th>
                        <th class="px-5 py-3.5 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60 text-slate-300">
                    @forelse($proyectos as $p)
                        <tr class="hover:bg-slate-800/40 transition">
                            <td class="px-5 py-3.5 font-bold text-white max-w-xs">
                                <a href="{{ route('servicios.proyectos.show', $p->id) }}" class="hover:text-cyan-400">
                                    {{ $p->nombre }}
                                </a>
                                @if($p->descripcion)
                                    <span class="block text-[10px] text-slate-400 font-normal truncate">{{ $p->descripcion }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-slate-300">
                                {{ $p->cliente->razon_social }}
                            </td>
                            <td class="px-5 py-3.5">
                                <div class="flex flex-wrap gap-1 max-w-xs">
                                    @forelse($p->instalaciones as $inst)
                                        <span class="px-2 py-0.5 rounded bg-slate-800 text-slate-300 text-[10px]">
                                            {{ $inst->nombre }}
                                        </span>
                                    @empty
                                        <span class="text-slate-500 text-[10px]">Sin instalaciones</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="px-5 py-3.5 whitespace-nowrap">
                                <div>{{ $p->fecha_inicio->format('d/m/Y') }}</div>
                                <div class="text-[10px] text-slate-400">
                                    Hasta {{ $p->fecha_fin ? $p->fecha_fin->format('d/m/Y') : 'Indefinido' }}
                                </div>
                            </td>
                            <td class="px-5 py-3.5 font-semibold text-white">
                                <span class="px-2 py-0.5 rounded bg-cyan-500/10 text-cyan-300 border border-cyan-500/20 text-[10px]">
                                    {{ $p->necesidadesServicio->count() }} necesidades
                                </span>
                            </td>
                            <td class="px-5 py-3.5">
                                @if($p->estado === 'activo')
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                        Activo
                                    </span>
                                @elseif($p->estado === 'borrador')
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-slate-700 text-slate-300">
                                        Borrador
                                    </span>
                                @elseif($p->estado === 'finalizado')
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-cyan-500/10 text-cyan-300">
                                        Finalizado
                                    </span>
                                @else
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-rose-500/10 text-rose-400">
                                        Cancelado
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-right space-x-1 whitespace-nowrap">
                                <a href="{{ route('servicios.proyectos.show', $p->id) }}"
                                    class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-cyan-400 font-medium inline-block text-[11px]">
                                    Ver Detalle
                                </a>
                                <button wire:click="abrirModalEditar({{ $p->id }})"
                                    class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 font-medium text-[11px]">
                                    Editar
                                </button>
                                <button wire:click="confirmarEliminar({{ $p->id }})"
                                    class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-rose-900/40 text-rose-400 font-medium text-[11px]">
                                    Eliminar
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-8 text-center text-slate-400">
                                No se encontraron proyectos de servicio registrados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($proyectos->hasPages())
            <div class="p-4 border-t border-slate-800">
                {{ $proyectos->links() }}
            </div>
        @endif
    </div>

    <!-- Modal Crear / Editar Proyecto -->
    @if($modalOpen)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-lg w-full p-6 shadow-2xl relative text-xs">
                <h3 class="text-base font-bold text-white mb-4">
                    {{ $proyectoId ? 'Editar Proyecto' : 'Nuevo Proyecto' }}
                </h3>
                <form wire:submit="guardarProyecto" class="space-y-4">
                    <div>
                        <label class="block text-slate-300 font-medium mb-1">Nombre del Proyecto *</label>
                        <input type="text" wire:model="nombre" required placeholder="Ej: Campaña Socorrismo Verano 2026"
                            class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Cliente *</label>
                            <select wire:model.live="cliente_id" required
                                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
                                <option value="">Seleccionar cliente...</option>
                                @foreach($clientes as $cli)
                                    <option value="{{ $cli->id }}">{{ $cli->razon_social }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Estado *</label>
                            <select wire:model="estado" required
                                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
                                <option value="borrador">Borrador</option>
                                <option value="activo">Activo</option>
                                <option value="finalizado">Finalizado</option>
                                <option value="cancelado">Cancelado</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Fecha Inicio *</label>
                            <input type="date" wire:model="fecha_inicio" required
                                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
                        </div>
                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Fecha Fin (Opcional)</label>
                            <input type="date" wire:model="fecha_fin"
                                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
                            @error('fecha_fin') <span class="text-rose-400 text-[10px]">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <!-- Selección de Instalaciones del Cliente -->
                    @if($instalacionesCliente->isNotEmpty())
                        <div class="p-3 bg-slate-950/60 rounded-xl border border-slate-800">
                            <label class="block text-slate-300 font-semibold mb-2">Instalaciones Incluidas</label>
                            <div class="space-y-1.5 max-h-32 overflow-y-auto">
                                @foreach($instalacionesCliente as $inst)
                                    <label class="flex items-center gap-2 text-slate-300">
                                        <input type="checkbox" value="{{ $inst->id }}" wire:model="instalacionesSeleccionadas"
                                            class="rounded border-slate-700 bg-slate-950 text-cyan-600 focus:ring-cyan-500">
                                        <span>{{ $inst->nombre }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div>
                        <label class="block text-slate-300 font-medium mb-1">Descripción</label>
                        <textarea wire:model="descripcion" rows="2"
                            class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500"></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-800">
                        <button type="button" wire:click="$set('modalOpen', false)"
                            class="px-4 py-2 rounded-xl bg-slate-800 text-slate-300">Cancelar</button>
                        <button type="submit"
                            class="px-4 py-2 rounded-xl bg-cyan-600 hover:bg-cyan-500 text-white font-semibold">Guardar Proyecto</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Modal Confirmar Eliminación Proyecto -->
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
                        <h4 class="text-sm font-bold text-white">¿Eliminar proyecto de servicio?</h4>
                        <p class="text-[11px] text-slate-400">{{ $proyectoToDeleteNombre }}</p>
                    </div>
                </div>

                <div class="bg-slate-950/60 p-3.5 rounded-xl border border-slate-800 mb-4 space-y-2">
                    @if($proyectoTieneRegistros)
                        <div class="p-2.5 rounded-lg bg-amber-500/10 border border-amber-500/20 text-amber-300 text-[11px]">
                            ⚠ Este proyecto cuenta con necesidades de servicio o movimientos contables asociados. Para preservar la trazabilidad, <strong>se marcará como Cancelado</strong>.
                        </div>
                    @else
                        <p class="text-slate-300 text-[11px]">
                            Este proyecto no tiene necesidades ni movimientos vinculados. Se eliminará permanentemente.
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
                        {{ $proyectoTieneRegistros ? 'Marcar como Cancelado' : 'Eliminar Proyecto' }}
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
