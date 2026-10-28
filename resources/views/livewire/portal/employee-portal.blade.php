<div class="space-y-6">
    <!-- Cabecera del Portal -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-sky-400"></span>
                <h1 class="text-xl font-black text-white">Portal del Empleado</h1>
            </div>
            <p class="text-xs text-slate-400 mt-1">
                Consulta tus turnos asignados, registra tus horas de trabajo efectivas y tramita solicitudes de ausencia.
            </p>
        </div>

        @if($empleado)
            <div class="flex items-center gap-2 text-xs">
                <button wire:click="$set('modalAusenciaOpen', true)"
                    class="px-4 py-2 rounded-xl font-semibold bg-sky-600 hover:bg-sky-500 text-white shadow-md transition flex items-center gap-1.5 shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    Solicitar Ausencia / Vacaciones
                </button>
            </div>
        @endif
    </div>

    @if(!$empleado)
        <div class="p-6 bg-slate-900 border border-slate-800 rounded-2xl text-center text-xs text-slate-400">
            <span class="text-base font-bold text-white block mb-1">Sin Ficha de Empleado Asociada</span>
            Tu usuario no tiene vinculada ninguna ficha de empleado. Contacta con un Administrador o Recursos Humanos para vincular tu cuenta.
        </div>
    @else
        <!-- Tarjeta de Perfil Rápido -->
        <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-4 flex flex-wrap items-center justify-between gap-4 text-xs">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-sky-500/20 text-sky-400 border border-sky-500/30 flex items-center justify-center font-bold text-sm">
                    {{ strtoupper(substr($empleado->nombre, 0, 1) . substr($empleado->apellidos, 0, 1)) }}
                </div>
                <div>
                    <span class="font-bold text-white text-sm block">{{ $empleado->nombre_completo }}</span>
                    <span class="text-slate-400 text-[11px]">{{ $empleado->categoria->nombre ?? 'Sin Categoría' }}</span>
                </div>
            </div>

            <div class="flex items-center gap-4 text-slate-300">
                <div>
                    <span class="text-slate-500 text-[10px] block uppercase">DNI / NIE</span>
                    <span class="font-mono">{{ $empleado->dni_nie ?: '—' }}</span>
                </div>
                <div>
                    <span class="text-slate-500 text-[10px] block uppercase">Turnos Programados</span>
                    <span class="font-bold text-white">{{ $turnosFuturos->count() }}</span>
                </div>
            </div>
        </div>

        <!-- Pestañas del Portal -->
        <div class="flex items-center gap-2 border-b border-slate-800 text-xs">
            <button wire:click="$set('activeTab', 'turnos')"
                class="px-4 py-3 font-semibold border-b-2 transition {{ $activeTab === 'turnos' ? 'border-sky-500 text-sky-400' : 'border-transparent text-slate-400 hover:text-white' }}">
                Mis Próximos Turnos ({{ $turnosFuturos->count() }})
            </button>
            <button wire:click="$set('activeTab', 'imputar')"
                class="px-4 py-3 font-semibold border-b-2 transition {{ $activeTab === 'imputar' ? 'border-indigo-500 text-indigo-400' : 'border-transparent text-slate-400 hover:text-white' }}">
                Imputar Partes de Horas ({{ $turnosParaImputar->count() }})
            </button>
            <button wire:click="$set('activeTab', 'ausencias')"
                class="px-4 py-3 font-semibold border-b-2 transition {{ $activeTab === 'ausencias' ? 'border-amber-500 text-amber-400' : 'border-transparent text-slate-400 hover:text-white' }}">
                Mis Solicitudes de Ausencia ({{ $misAusencias->count() }})
            </button>
        </div>

        <!-- Pestaña 1: Próximos Turnos -->
        @if($activeTab === 'turnos')
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 text-xs">
                @forelse($turnosFuturos as $asig)
                    @php $t = $asig->turno; @endphp
                    <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-4 shadow-xl space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-white text-sm">{{ $t->fecha->isoFormat('dddd D [de] MMMM') }}</span>
                            <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-sky-500/20 text-sky-300 font-mono">
                                {{ Carbon\Carbon::parse($t->hora_inicio)->format('H:i') }} - {{ Carbon\Carbon::parse($t->hora_fin)->format('H:i') }}
                            </span>
                        </div>
                        <div>
                            <span class="text-white font-medium block">{{ $t->instalacion->nombre }}</span>
                            <span class="text-slate-400 text-[11px]">{{ $t->actividad->nombre }}</span>
                        </div>
                        <div class="pt-2 border-t border-slate-800/80 flex items-center justify-between">
                            <span class="text-[10px] text-slate-500">{{ $t->duracionHoras() }} h programadas</span>
                            <button wire:click="abrirModalImputar({{ $asig->id }})"
                                class="text-sky-400 hover:underline font-semibold text-[11px]">
                                Imputar Horas →
                            </button>
                        </div>
                    </div>
                @empty
                    <div class="col-span-3 p-8 text-center bg-slate-900/50 rounded-2xl border border-slate-800 text-slate-400">
                        No tienes turnos programados en los próximos días.
                    </div>
                @endforelse
            </div>
        @endif

        <!-- Pestaña 2: Imputar Horas -->
        @if($activeTab === 'imputar')
            <div class="bg-slate-900/80 rounded-2xl border border-slate-800 overflow-hidden shadow-xl text-xs">
                <div class="p-4 border-b border-slate-800">
                    <span class="font-bold text-white">Historial de Partes y Turnos para Imputar</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead class="bg-slate-950/70 border-b border-slate-800 text-[11px] uppercase tracking-wider text-slate-400">
                            <tr>
                                <th class="px-5 py-3.5">Fecha</th>
                                <th class="px-5 py-3.5">Instalación y Servicio</th>
                                <th class="px-5 py-3.5">Horario Previsto</th>
                                <th class="px-5 py-3.5">Horas Reales</th>
                                <th class="px-5 py-3.5">Estado Validación</th>
                                <th class="px-5 py-3.5 text-right">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60 text-slate-300">
                            @forelse($turnosParaImputar as $asig)
                                @php $t = $asig->turno; @endphp
                                <tr class="hover:bg-slate-800/40">
                                    <td class="px-5 py-3.5 font-bold text-white whitespace-nowrap">
                                        {{ $t->fecha->format('d/m/Y') }}
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <span class="text-white font-medium block">{{ $t->instalacion->nombre }}</span>
                                        <span class="text-[10px] text-slate-400">{{ $t->actividad->nombre }}</span>
                                    </td>
                                    <td class="px-5 py-3.5 whitespace-nowrap font-mono text-[11px]">
                                        {{ Carbon\Carbon::parse($t->hora_inicio)->format('H:i') }} - {{ Carbon\Carbon::parse($t->hora_fin)->format('H:i') }}
                                        ({{ $t->duracionHoras() }} h)
                                    </td>
                                    <td class="px-5 py-3.5 whitespace-nowrap">
                                        @if($asig->registroHora)
                                            <span class="font-bold text-white">{{ $asig->registroHora->horas_computables }} h computables</span>
                                            <span class="text-[10px] text-slate-400 block">
                                                {{ Carbon\Carbon::parse($asig->registroHora->inicio_real)->format('H:i') }}-{{ Carbon\Carbon::parse($asig->registroHora->fin_real)->format('H:i') }}
                                                (Descanso: {{ $asig->registroHora->descanso_minutos }}m)
                                            </span>
                                        @else
                                            <span class="text-rose-400 font-semibold italic">Pendiente de imputar</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3.5 whitespace-nowrap">
                                        @if($asig->registroHora)
                                            @if($asig->registroHora->estado === 'validado')
                                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                                    ✓ Validado
                                                </span>
                                            @else
                                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-500/10 text-amber-400 border border-amber-500/20">
                                                    ⏳ En Revisión
                                                </span>
                                            @endif
                                        @else
                                            <span class="text-slate-500 text-[10px]">—</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3.5 text-right whitespace-nowrap">
                                        @if(!$asig->registroHora || $asig->registroHora->estado !== 'validado')
                                            <button wire:click="abrirModalImputar({{ $asig->id }})"
                                                class="px-2.5 py-1 rounded-lg bg-sky-600 hover:bg-sky-500 text-white font-semibold text-[11px]">
                                                {{ $asig->registroHora ? 'Modificar Parte' : 'Imputar Horas' }}
                                            </button>
                                        @else
                                            <span class="text-[11px] text-slate-500">Bloqueado (Validado)</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-5 py-8 text-center text-slate-400">
                                        No hay turnos pendientes de imputación.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <!-- Pestaña 3: Solicitudes de Ausencia -->
        @if($activeTab === 'ausencias')
            <div class="bg-slate-900/80 rounded-2xl border border-slate-800 overflow-hidden shadow-xl text-xs">
                <div class="p-4 border-b border-slate-800 flex items-center justify-between">
                    <span class="font-bold text-white">Mis Solicitudes de Ausencia y Permisos</span>
                    <button wire:click="$set('modalAusenciaOpen', true)" class="text-sky-400 hover:underline font-semibold text-xs">
                        + Nueva Solicitud
                    </button>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead class="bg-slate-950/70 border-b border-slate-800 text-[11px] uppercase tracking-wider text-slate-400">
                            <tr>
                                <th class="px-5 py-3.5">Tipo</th>
                                <th class="px-5 py-3.5">Inicio</th>
                                <th class="px-5 py-3.5">Fin</th>
                                <th class="px-5 py-3.5">Motivo</th>
                                <th class="px-5 py-3.5">Estado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60 text-slate-300">
                            @forelse($misAusencias as $a)
                                <tr class="hover:bg-slate-800/40">
                                    <td class="px-5 py-3.5 font-bold text-white capitalize">{{ $a->tipo }}</td>
                                    <td class="px-5 py-3.5 whitespace-nowrap">{{ $a->fecha_hora_inicio->format('d/m/Y H:i') }}</td>
                                    <td class="px-5 py-3.5 whitespace-nowrap">{{ $a->fecha_hora_fin->format('d/m/Y H:i') }}</td>
                                    <td class="px-5 py-3.5 text-slate-400 max-w-xs truncate">{{ $a->observaciones ?: '—' }}</td>
                                    <td class="px-5 py-3.5">
                                        @if($a->estado === 'aprobada')
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                                ✓ Aprobada
                                            </span>
                                        @elseif($a->estado === 'pendiente')
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-500/10 text-amber-400 border border-amber-500/20">
                                                ⏳ Pendiente RRHH
                                            </span>
                                        @else
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-500/10 text-rose-400 border border-rose-500/20">
                                                ✕ Rechazada
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-5 py-8 text-center text-slate-400">
                                        No has solicitado ausencias o vacaciones.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <!-- Modal Imputar Horas -->
        @if($modalImputarOpen)
            <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
                <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6 shadow-2xl relative text-xs">
                    <h3 class="text-base font-bold text-white mb-2">Imputar Horas Realizadas</h3>
                    @if($turnoParaImputar)
                        <p class="text-slate-400 text-[11px] mb-4">
                            {{ $turnoParaImputar->instalacion->nombre }} · {{ $turnoParaImputar->fecha->format('d/m/Y') }}
                        </p>
                    @endif

                    <form wire:submit="guardarImputacion" class="space-y-4">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-slate-300 font-medium mb-1">Hora Inicio Real *</label>
                                <input type="datetime-local" wire:model="inicio_real" required
                                    class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
                            </div>
                            <div>
                                <label class="block text-slate-300 font-medium mb-1">Hora Fin Real *</label>
                                <input type="datetime-local" wire:model="fin_real" required
                                    class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
                            </div>
                        </div>

                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Descanso No Computable (Minutos) *</label>
                            <input type="number" min="0" wire:model="descanso_minutos" required
                                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
                            <span class="text-[10px] text-slate-500 mt-0.5 block">Ejemplo: 30 minutos de comida o descanso intermedio</span>
                        </div>

                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Observaciones</label>
                            <textarea wire:model="observaciones_horas" rows="2" placeholder="Incidencias o comentarios de la jornada..."
                                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500"></textarea>
                        </div>

                        <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-800">
                            <button type="button" wire:click="$set('modalImputarOpen', false)"
                                class="px-4 py-2 rounded-xl bg-slate-800 text-slate-300">Cancelar</button>
                            <button type="submit"
                                class="px-4 py-2 rounded-xl bg-sky-600 hover:bg-sky-500 text-white font-semibold">Guardar Parte</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        <!-- Modal Solicitar Ausencia -->
        @if($modalAusenciaOpen)
            <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
                <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6 shadow-2xl relative text-xs">
                    <h3 class="text-base font-bold text-white mb-2">Solicitar Ausencia / Permiso</h3>
                    <p class="text-slate-400 text-[11px] mb-4">
                        Tu solicitud quedará pendiente de validación por el departamento de Recursos Humanos.
                    </p>

                    <form wire:submit="solicitarAusencia" class="space-y-4">
                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Tipo de Solicitud *</label>
                            <select wire:model="tipo_ausencia" required
                                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
                                <option value="vacaciones">Vacaciones</option>
                                <option value="baja">Baja Médica / Cita Especialista</option>
                                <option value="permiso">Permiso Retribuido (Exámenes / Deber público)</option>
                                <option value="otro">Otro Motivo</option>
                            </select>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-slate-300 font-medium mb-1">Inicio *</label>
                                <input type="datetime-local" wire:model="ausencia_inicio" required
                                    class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
                            </div>
                            <div>
                                <label class="block text-slate-300 font-medium mb-1">Fin *</label>
                                <input type="datetime-local" wire:model="ausencia_fin" required
                                    class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
                            </div>
                        </div>

                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Motivo / Justificación</label>
                            <textarea wire:model="motivo_ausencia" rows="2"
                                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500"></textarea>
                        </div>

                        <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-800">
                            <button type="button" wire:click="$set('modalAusenciaOpen', false)"
                                class="px-4 py-2 rounded-xl bg-slate-800 text-slate-300">Cancelar</button>
                            <button type="submit"
                                class="px-4 py-2 rounded-xl bg-sky-600 hover:bg-sky-500 text-white font-semibold">Enviar Solicitud</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    @endif
</div>
