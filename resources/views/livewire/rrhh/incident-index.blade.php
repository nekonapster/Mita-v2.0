<div class="space-y-6">
    <!-- Cabecera -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-black text-white flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-rose-400"></span>
                Recursos Humanos · Registro de Incidencias
            </h1>
            <p class="text-xs text-slate-400 mt-1">
                Seguimiento operativo de problemas en instalaciones, empleados o proyectos vinculados.
            </p>
        </div>

        <button wire:click="abrirModalCrear"
            class="px-4 py-2 rounded-xl text-xs font-semibold bg-rose-600 hover:bg-rose-500 text-white shadow-md transition flex items-center gap-1.5 shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            Nueva Incidencia
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

    @if (session()->has('success'))
        <div class="p-3 bg-emerald-500/10 border border-emerald-500/30 rounded-xl text-emerald-400 text-xs">
            {{ session('success') }}
        </div>
    @endif

    <!-- Filtros -->
    <div class="bg-slate-900/80 p-4 rounded-2xl border border-slate-800 grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
        <div>
            <label class="block text-slate-400 mb-1 font-medium">Estado</label>
            <select wire:model.live="estadoFiltro"
                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
                <option value="">Todos los estados</option>
                <option value="abierta">Abiertas</option>
                <option value="en_seguimiento">En Seguimiento</option>
                <option value="resuelta">Resueltas</option>
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

        <div>
            <label class="block text-slate-400 mb-1 font-medium">Instalación</label>
            <select wire:model.live="instalacionFiltro"
                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
                <option value="">Todas las instalaciones</option>
                @foreach($instalaciones as $inst)
                    <option value="{{ $inst->id }}">{{ $inst->nombre }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <!-- Listado de Incidencias -->
    <div class="bg-slate-900/80 rounded-2xl border border-slate-800 overflow-hidden shadow-xl text-xs">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="bg-slate-950/70 border-b border-slate-800 text-[11px] uppercase tracking-wider text-slate-400">
                    <tr>
                        <th class="px-5 py-3.5">Fecha</th>
                        <th class="px-5 py-3.5">Título e Información</th>
                        <th class="px-5 py-3.5">Elementos Vinculados</th>
                        <th class="px-5 py-3.5">Registrado Por</th>
                        <th class="px-5 py-3.5">Estado</th>
                        <th class="px-5 py-3.5 text-right">Cambiar Estado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60 text-slate-300">
                    @forelse($incidencias as $inc)
                        <tr class="hover:bg-slate-800/40 transition">
                            <td class="px-5 py-3.5 whitespace-nowrap font-medium text-slate-300">
                                {{ $inc->fecha->format('d/m/Y') }}
                            </td>
                            <td class="px-5 py-3.5 max-w-sm">
                                <span class="font-bold text-white block">{{ $inc->titulo }}</span>
                                <p class="text-slate-400 text-[11px] mt-0.5">{{ $inc->descripcion }}</p>
                            </td>
                            <td class="px-5 py-3.5 space-y-0.5 text-[11px]">
                                @if($inc->empleado)
                                    <div class="text-slate-300 flex items-center gap-1">
                                        <span class="text-slate-500">Empleado:</span> {{ $inc->empleado->nombre_completo }}
                                    </div>
                                @endif
                                @if($inc->instalacion)
                                    <div class="text-slate-300 flex items-center gap-1">
                                        <span class="text-slate-500">Instalación:</span> {{ $inc->instalacion->nombre }}
                                    </div>
                                @endif
                                @if($inc->proyecto)
                                    <div class="text-slate-300 flex items-center gap-1">
                                        <span class="text-slate-500">Proyecto:</span> {{ $inc->proyecto->nombre }}
                                    </div>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-slate-400">
                                {{ $inc->creador->name ?? 'Usuario' }}
                            </td>
                            <td class="px-5 py-3.5">
                                @if($inc->estado === 'abierta')
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-rose-500/10 text-rose-400 border border-rose-500/20">
                                        Abierta
                                    </span>
                                @elseif($inc->estado === 'en_seguimiento')
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/10 text-amber-400 border border-amber-500/20">
                                        En seguimiento
                                    </span>
                                @else
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                        Resuelta
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-right whitespace-nowrap space-x-1">
                                @if($inc->estado !== 'abierta')
                                    <button wire:click="cambiarEstado({{ $inc->id }}, 'abierta')"
                                        class="px-2 py-1 rounded bg-slate-800 text-rose-400 hover:bg-slate-700 text-[10px]">
                                        Reabrir
                                    </button>
                                @endif
                                @if($inc->estado !== 'en_seguimiento')
                                    <button wire:click="cambiarEstado({{ $inc->id }}, 'en_seguimiento')"
                                        class="px-2 py-1 rounded bg-slate-800 text-amber-400 hover:bg-slate-700 text-[10px]">
                                        Seguimiento
                                    </button>
                                @endif
                                @if($inc->estado !== 'resuelta')
                                    <button wire:click="cambiarEstado({{ $inc->id }}, 'resuelta')"
                                        class="px-2 py-1 rounded bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-[10px]">
                                        Resolver
                                    </button>
                                @endif
                                <button wire:click="eliminar({{ $inc->id }})"
                                    wire:confirm="¿Seguro que deseas eliminar esta incidencia?"
                                    title="Eliminar incidencia"
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
                                No se encontraron incidencias con los filtros aplicados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($incidencias->hasPages())
            <div class="p-4 border-t border-slate-800">
                {{ $incidencias->links() }}
            </div>
        @endif
    </div>

    <!-- Modal Registrar Incidencia -->
    @if($modalCrearOpen)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-lg w-full p-6 shadow-2xl relative text-xs">
                <h3 class="text-base font-bold text-white mb-2">Registrar Nueva Incidencia</h3>
                <p class="text-slate-400 text-[11px] mb-4">
                    Debe estar vinculada al menos a un empleado, una instalación o un proyecto.
                </p>

                @error('vinculacion')
                    <div class="mb-3 p-2.5 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-400 text-[11px]">
                        {{ $message }}
                    </div>
                @enderror

                <form wire:submit="guardarIncidencia" class="space-y-4">
                    <div>
                        <label class="block text-slate-300 font-medium mb-1">Título de la Incidencia *</label>
                        <input type="text" wire:model="titulo" required placeholder="Ej: Rotura de corchera en calle 2"
                            class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Fecha *</label>
                            <input type="date" wire:model="fecha" required
                                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
                        </div>
                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Estado Inicial *</label>
                            <select wire:model="estado" required
                                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
                                <option value="abierta">Abierta</option>
                                <option value="en_seguimiento">En Seguimiento</option>
                                <option value="resuelta">Resuelta</option>
                            </select>
                        </div>
                    </div>

                    <div class="p-3 bg-slate-950/60 rounded-xl border border-slate-800 space-y-3">
                        <span class="text-[11px] font-bold text-slate-300 uppercase tracking-wider block">
                            Elementos a Vincular (al menos uno)
                        </span>
                        
                        <div>
                            <label class="block text-slate-400 mb-1">Empleado Relacionado</label>
                            <select wire:model="empleado_id"
                                class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
                                <option value="">Ninguno</option>
                                @foreach($empleados as $e)
                                    <option value="{{ $e->id }}">{{ $e->nombre_completo }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-slate-400 mb-1">Instalación Relacionada</label>
                            <select wire:model="instalacion_id"
                                class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
                                <option value="">Ninguna</option>
                                @foreach($instalaciones as $inst)
                                    <option value="{{ $inst->id }}">{{ $inst->nombre }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-slate-400 mb-1">Proyecto Relacionado</label>
                            <select wire:model="proyecto_id"
                                class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
                                <option value="">Ninguno</option>
                                @foreach($proyectos as $p)
                                    <option value="{{ $p->id }}">{{ $p->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-slate-300 font-medium mb-1">Descripción Detallada *</label>
                        <textarea wire:model="descripcion" rows="3" required placeholder="Describe lo ocurrido y acciones tomadas..."
                            class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500"></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-800">
                        <button type="button" wire:click="$set('modalCrearOpen', false)"
                            class="px-4 py-2 rounded-xl bg-slate-800 text-slate-300">Cancelar</button>
                        <button type="submit"
                            class="px-4 py-2 rounded-xl bg-rose-600 hover:bg-rose-500 text-white font-semibold">Guardar Incidencia</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
