<div class="space-y-6">
    <!-- Cabecera -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-black text-white flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-cyan-400"></span>
                Servicios · Necesidades de Servicio (Cobertura Prevista)
            </h1>
            <p class="text-xs text-slate-400 mt-1">
                Define los patrones recurrentes de horarios, actividades y plazas necesarias para generar turnos reales.
            </p>
        </div>

        <button wire:click="abrirModalCrear"
            class="px-4 py-2 rounded-xl text-xs font-semibold bg-cyan-600 hover:bg-cyan-500 text-white shadow-md transition flex items-center gap-1.5 shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            Nueva Necesidad
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
                    <option value="{{ $p->id }}">{{ $p->nombre }} ({{ $p->cliente->razon_social }})</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-slate-400 mb-1 font-medium">Instalación</label>
            <select wire:model.live="instalacionFiltro"
                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
                <option value="">Todas las instalaciones</option>
                @foreach($proyectos->flatMap->instalaciones->unique('id') as $inst)
                    <option value="{{ $inst->id }}">{{ $inst->nombre }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <!-- Listado de Necesidades -->
    <div class="bg-slate-900/80 rounded-2xl border border-slate-800 overflow-hidden shadow-xl text-xs">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="bg-slate-950/70 border-b border-slate-800 text-[11px] uppercase tracking-wider text-slate-400">
                    <tr>
                        <th class="px-5 py-3.5">Proyecto e Instalación</th>
                        <th class="px-5 py-3.5">Actividad y Perfil Requerido</th>
                        <th class="px-5 py-3.5">Días</th>
                        <th class="px-5 py-3.5">Horario</th>
                        <th class="px-5 py-3.5">Plazas</th>
                        <th class="px-5 py-3.5">Vigencia</th>
                        <th class="px-5 py-3.5 text-right">Operaciones de Turnos</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60 text-slate-300">
                    @forelse($necesidades as $n)
                        <tr class="hover:bg-slate-800/40 transition">
                            <td class="px-5 py-3.5 font-bold text-white max-w-xs">
                                <div>{{ $n->proyecto->nombre }}</div>
                                <span class="text-[11px] text-cyan-400 font-normal block">{{ $n->instalacion->nombre }}</span>
                            </td>
                            <td class="px-5 py-3.5">
                                <span class="font-semibold text-slate-200 block">{{ $n->actividad->nombre }}</span>
                                <span class="text-[10px] text-slate-400">{{ $n->categoria->nombre }}</span>
                            </td>
                            <td class="px-5 py-3.5">
                                @php $diasMap = [1 => 'L', 2 => 'M', 3 => 'X', 4 => 'J', 5 => 'V', 6 => 'S', 7 => 'D']; @endphp
                                <div class="flex gap-1">
                                    @foreach([1, 2, 3, 4, 5, 6, 7] as $d)
                                        <span class="w-5 h-5 rounded flex items-center justify-center font-bold text-[10px] {{ in_array($d, $n->dias_semana ?? []) ? 'bg-cyan-600 text-white' : 'bg-slate-800 text-slate-500' }}">
                                            {{ $diasMap[$d] }}
                                        </span>
                                    @endforeach
                                </div>
                            </td>
                            <td class="px-5 py-3.5 whitespace-nowrap font-mono text-[11px]">
                                {{ substr($n->hora_inicio, 0, 5) }} - {{ substr($n->hora_fin, 0, 5) }}
                                @if($n->cruza_medianoche)
                                    <span class="px-1.5 py-0.2 rounded text-[9px] bg-purple-500/20 text-purple-300 ml-1 font-sans">
                                        Noche (+1d)
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 font-bold text-white whitespace-nowrap">
                                {{ $n->plazas_necesarias }} plaza(s)
                            </td>
                            <td class="px-5 py-3.5 text-slate-400 text-[11px] whitespace-nowrap">
                                {{ $n->fecha_inicio_vigencia->format('d/m/Y') }}<br>
                                a {{ $n->fecha_fin_vigencia->format('d/m/Y') }}
                            </td>
                            <td class="px-5 py-3.5 text-right whitespace-nowrap space-x-1">
                                <button wire:click="abrirModalGenerar({{ $n->id }})"
                                    class="px-2.5 py-1 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-semibold transition text-[11px]">
                                    ⚡ Generar Turnos
                                </button>
                                <button wire:click="abrirModalSync({{ $n->id }})" title="Comprobar diferencias con turnos existentes"
                                    class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-amber-300 font-medium transition text-[11px]">
                                    Sincronizar
                                </button>
                                <button wire:click="abrirModalEditar({{ $n->id }})"
                                    class="px-2 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 font-medium transition text-[11px]">
                                    Editar
                                </button>
                                <button wire:click="confirmarEliminar({{ $n->id }})"
                                    class="px-2 py-1 rounded-lg bg-slate-800 hover:bg-rose-900/40 text-rose-400 font-medium transition text-[11px]">
                                    Eliminar
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-8 text-center text-slate-400">
                                No hay necesidades de servicio registradas.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($necesidades->hasPages())
            <div class="p-4 border-t border-slate-800">
                {{ $necesidades->links() }}
            </div>
        @endif
    </div>

    <!-- Modal Crear / Editar Necesidad -->
    @if($modalOpen)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-lg w-full p-6 shadow-2xl relative text-xs">
                <h3 class="text-base font-bold text-white mb-4">
                    {{ $necesidadId ? 'Editar Necesidad de Servicio' : 'Nueva Necesidad de Servicio' }}
                </h3>
                <form wire:submit="guardar" class="space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Proyecto *</label>
                            <select wire:model.live="proyecto_id" required
                                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
                                <option value="">Selecciona...</option>
                                @foreach($proyectos as $p)
                                    <option value="{{ $p->id }}">{{ $p->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Instalación *</label>
                            <select wire:model="instalacion_id" required
                                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
                                <option value="">Selecciona...</option>
                                @foreach($instalacionesDelProyecto as $inst)
                                    <option value="{{ $inst->id }}">{{ $inst->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Actividad / Servicio *</label>
                            <select wire:model="actividad_id" required
                                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
                                @foreach($actividades as $act)
                                    <option value="{{ $act->id }}">{{ $act->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Categoría Requerida *</label>
                            <select wire:model="categoria_id" required
                                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
                                @foreach($categorias as $c)
                                    <option value="{{ $c->id }}">{{ $c->nombre }} ({{ $c->codigo }})</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <!-- Días de la semana -->
                    <div>
                        <label class="block text-slate-300 font-medium mb-1.5">Días de la semana requeridos *</label>
                        <div class="grid grid-cols-7 gap-1">
                            @foreach([1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'] as $num => $nombreDia)
                                <label class="flex flex-col items-center justify-center p-2 rounded-xl bg-slate-950 border border-slate-800 cursor-pointer hover:border-cyan-500 transition">
                                    <input type="checkbox" value="{{ $num }}" wire:model="dias_semana"
                                        class="rounded border-slate-700 bg-slate-900 text-cyan-600 focus:ring-cyan-500">
                                    <span class="text-[10px] text-slate-300 font-bold mt-1">{{ substr($nombreDia, 0, 3) }}</span>
                                </label>
                            @endforeach
                        </div>
                        @error('dias_semana') <span class="text-rose-400 text-[10px]">{{ $message }}</span> @enderror
                    </div>

                    <!-- Horarios y Cruce Medianoche -->
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Hora Inicio *</label>
                            <input type="time" wire:model="hora_inicio" required
                                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500 font-mono">
                        </div>
                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Hora Fin *</label>
                            <input type="time" wire:model="hora_fin" required
                                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500 font-mono">
                        </div>
                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Plazas *</label>
                            <input type="number" min="1" max="20" wire:model="plazas_necesarias" required
                                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <input type="checkbox" id="cruzaNoche" wire:model="cruza_medianoche"
                            class="rounded border-slate-700 bg-slate-950 text-cyan-600 focus:ring-cyan-500">
                        <label for="cruzaNoche" class="text-slate-300 font-medium">
                            El turno cruza la medianoche (termina al día siguiente, ej: 22:00 a 06:00)
                        </label>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Vigencia Desde *</label>
                            <input type="date" wire:model="fecha_inicio_vigencia" required
                                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
                        </div>
                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Vigencia Hasta *</label>
                            <input type="date" wire:model="fecha_fin_vigencia" required
                                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
                        </div>
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
                            class="px-4 py-2 rounded-xl bg-cyan-600 hover:bg-cyan-500 text-white font-semibold">Guardar Necesidad</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Modal Generar Turnos -->
    @if($modalGenerarOpen)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6 shadow-2xl relative text-xs">
                <h3 class="text-base font-bold text-white mb-2">Generador Automático de Turnos</h3>
                <p class="text-slate-400 text-[11px] mb-4">
                    Crea los turnos concretos para el rango seleccionado evitando duplicados con los ya existentes.
                </p>

                <form wire:submit="generarTurnos" class="space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Desde Fecha *</label>
                            <input type="date" wire:model="genFechaDesde" required
                                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
                        </div>
                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Hasta Fecha *</label>
                            <input type="date" wire:model="genFechaHasta" required
                                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
                        </div>
                    </div>

                    <div>
                        <label class="block text-slate-300 font-medium mb-1">Estado de los Turnos *</label>
                        <select wire:model="genEstado" required
                            class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
                            <option value="publicado">Publicado (visible en cuadrante operativo)</option>
                            <option value="borrador">Borrador (pendiente de publicación)</option>
                        </select>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-800">
                        <button type="button" wire:click="$set('modalGenerarOpen', false)"
                            class="px-4 py-2 rounded-xl bg-slate-800 text-slate-300">Cancelar</button>
                        <button type="submit"
                            class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-semibold">
                            Generar Turnos Ahora
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Modal Previsualización de Sincronización -->
    @if($modalSyncOpen && $syncPreviewData)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-lg w-full p-6 shadow-2xl relative text-xs space-y-4">
                <div>
                    <h3 class="text-base font-bold text-white">Previsualización de Cambios en Turnos</h3>
                    <p class="text-slate-400 text-[11px] mt-1">
                        Modificar una necesidad no altera silenciosamente turnos publicados con empleados ya asignados.
                    </p>
                </div>

                <div class="grid grid-cols-3 gap-2">
                    <div class="p-3 rounded-xl bg-slate-950/80 border border-slate-800 text-center">
                        <span class="text-slate-400 block text-[10px]">Total Futuros</span>
                        <span class="text-lg font-bold text-white">{{ $syncPreviewData['total_turnos'] }}</span>
                    </div>
                    <div class="p-3 rounded-xl bg-slate-950/80 border border-slate-800 text-center">
                        <span class="text-slate-400 block text-[10px]">Borradores</span>
                        <span class="text-lg font-bold text-cyan-400">{{ $syncPreviewData['borradores_afectados'] }}</span>
                    </div>
                    <div class="p-3 rounded-xl bg-slate-950/80 border border-slate-800 text-center">
                        <span class="text-slate-400 block text-[10px]">Publicados</span>
                        <span class="text-lg font-bold text-amber-400">{{ $syncPreviewData['publicados_afectados'] }}</span>
                    </div>
                </div>

                @if($syncPreviewData['asignaciones_en_riesgo'] > 0)
                    <div class="p-3 rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-300">
                        <strong>Atención:</strong> Hay {{ $syncPreviewData['asignaciones_en_riesgo'] }} asignaciones activas de empleados en turnos ya publicados. Si sincronizas los publicados, sus horarios se actualizarán.
                    </div>
                @endif

                <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-800">
                    <button type="button" wire:click="$set('modalSyncOpen', false)"
                        class="px-4 py-2 rounded-xl bg-slate-800 text-slate-300">Cerrar</button>
                    
                    <button type="button" wire:click="confirmarSync(false)"
                        class="px-4 py-2 rounded-xl bg-slate-700 hover:bg-slate-600 text-white font-semibold">
                        Sincronizar Solo Borradores
                    </button>

                    <button type="button" wire:click="confirmarSync(true)"
                        class="px-4 py-2 rounded-xl bg-amber-600 hover:bg-amber-500 text-white font-semibold">
                        Sincronizar Todo (Incluidos Publicados)
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal Confirmar Eliminación Necesidad -->
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
                        <h4 class="text-sm font-bold text-white">¿Eliminar necesidad de servicio?</h4>
                        <p class="text-[11px] text-slate-400">Cobertura y plantilla requerida</p>
                    </div>
                </div>

                <div class="bg-slate-950/60 p-3.5 rounded-xl border border-slate-800 mb-4 space-y-2">
                    @if($turnosAsociadosCount > 0)
                        <div class="p-2.5 rounded-lg bg-amber-500/10 border border-amber-500/20 text-amber-300 text-[11px]">
                            ⚠ Esta necesidad tiene <strong>{{ $turnosAsociadosCount }} turnos generados</strong>. Al eliminar la necesidad, se cancelarán los turnos asignados y se eliminarán los turnos vacíos en borrador.
                        </div>
                    @else
                        <p class="text-slate-300 text-[11px]">
                            Esta necesidad no tiene turnos generados. Se eliminará inmediatamente.
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
                        Confirmar Eliminación
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
