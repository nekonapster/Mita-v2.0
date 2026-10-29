<div class="space-y-6">
    <!-- Migas de pan y Cabecera de Ficha -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs text-slate-400 mb-1">
                <a href="{{ route('rrhh.empleados') }}" class="hover:text-cyan-400 transition">← Plantilla de
                    Empleados</a>
                <span>/</span>
                <span class="text-slate-300">Ficha de Empleado</span>
            </div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-black text-white">{{ $empleado->nombre_completo }}</h1>
                @if ($empleado->activo)
                    <span
                        class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                        Activo
                    </span>
                @else
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-700 text-slate-400">
                        Inactivo
                    </span>
                @endif
                <span
                    class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-cyan-500/10 text-cyan-300 border border-cyan-500/20">
                    {{ $empleado->categoria->nombre ?? 'Sin Categoría' }}
                </span>
            </div>
        </div>

        <div class="flex items-center gap-2 text-xs">
            <button wire:click="$set('modalContratoOpen', true)"
                class="px-3 py-2 rounded-xl font-semibold bg-slate-800 hover:bg-slate-700 text-cyan-400 border border-slate-700 transition">
                + Añadir Contrato
            </button>
            <button wire:click="$set('modalAusenciaOpen', true)"
                class="px-3 py-2 rounded-xl font-semibold bg-slate-800 hover:bg-slate-700 text-amber-400 border border-slate-700 transition">
                + Registrar Ausencia
            </button>
            <button wire:click="$set('modalTarifaOpen', true)"
                class="px-3 py-2 rounded-xl font-semibold bg-slate-800 hover:bg-slate-700 text-indigo-400 border border-slate-700 transition">
                + Tarifa Coste
            </button>
        </div>
    </div>

    @if ($feedbackMessage)
        <div
            class="p-3 bg-emerald-500/10 border border-emerald-500/30 rounded-xl text-emerald-400 text-xs flex items-center justify-between">
            <span>{{ $feedbackMessage }}</span>
            <button wire:click="$set('feedbackMessage', null)"
                class="text-emerald-400 hover:text-emerald-300 font-bold ml-2">✕</button>
        </div>
    @endif

    @if ($feedbackError)
        <div
            class="p-3 bg-rose-500/10 border border-rose-500/30 rounded-xl text-rose-400 text-xs flex items-center justify-between">
            <span>{{ $feedbackError }}</span>
            <button wire:click="$set('feedbackError', null)"
                class="text-rose-400 hover:text-rose-300 font-bold ml-2">✕</button>
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

    <!-- Resumen Superior del Empleado -->
    <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-5 grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs">
        <div>
            <span class="text-slate-400 block text-[11px]">DNI / NIE</span>
            <span
                class="font-mono font-semibold text-white mt-0.5 block">{{ $empleado->dni_nie ?: 'No especificado' }}</span>
        </div>
        <div>
            <span class="text-slate-400 block text-[11px]">Correo Electrónico</span>
            <span
                class="font-medium text-white mt-0.5 block truncate">{{ $empleado->email ?: 'No especificado' }}</span>
        </div>
        <div>
            <span class="text-slate-400 block text-[11px]">Teléfono</span>
            <span class="font-medium text-white mt-0.5 block">{{ $empleado->telefono ?: 'No especificado' }}</span>
        </div>
        <div>
            <span class="text-slate-400 block text-[11px]">Usuario de Acceso</span>
            @if ($empleado->user)
                <span class="font-semibold text-emerald-400 mt-0.5 block">Vinculado
                    ({{ $empleado->user->email }})</span>
            @else
                <span class="text-slate-400 mt-0.5 block italic">Sin usuario vinculado</span>
            @endif
        </div>
    </div>
    <!-- Resumen mensual de horas -->
    <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-5 space-y-5">

        <!-- Cabecera y selector de mes -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">

            <div>
                <h2 class="text-lg font-bold text-white">
                    Resumen de Horas
                </h2>

                <p class="text-xs text-slate-400 mt-1">
                    Cómputo individual de horas del trabajador
                </p>
            </div>

            <div class="flex items-center gap-3">

                <label for="mesResumen" onclick="document.getElementById('mesResumen').showPicker()"
                    class="text-xs font-medium text-slate-400 cursor-pointer">
                    Período:
                </label>

                <input id="mesResumen" type="month" wire:model.change.live="mesResumen" style="color-scheme: dark;"
                    class="bg-slate-950 border border-slate-700
               rounded-xl px-3 py-2 text-white text-sm
               cursor-pointer
               focus:border-cyan-500 focus:outline-none">
            </div>
        </div>

        <!-- Tarjetas de resumen -->
        @php
            $tarjetasResumen = [
                [
                    'titulo' => 'Planificadas',
                    'campo' => 'horas_planificadas',
                    'color' => 'text-cyan-400',
                    'borde' => 'border-cyan-500/20',
                ],
                [
                    'titulo' => 'Realizadas',
                    'campo' => 'horas_realizadas',
                    'color' => 'text-emerald-400',
                    'borde' => 'border-emerald-500/20',
                ],
                [
                    'titulo' => 'Pendientes',
                    'campo' => 'horas_pendientes',
                    'color' => 'text-amber-400',
                    'borde' => 'border-amber-500/20',
                ],
                [
                    'titulo' => 'Canceladas',
                    'campo' => 'horas_canceladas',
                    'color' => 'text-rose-400',
                    'borde' => 'border-rose-500/20',
                ],
                [
                    'titulo' => 'Borrador',
                    'campo' => 'horas_borrador',
                    'color' => 'text-slate-400',
                    'borde' => 'border-slate-700',
                ],
            ];
        @endphp

        <div class="grid grid-cols-2 lg:grid-cols-5 gap-3">

            @foreach ($tarjetasResumen as $tarjeta)
                <div class="bg-slate-950/60 border rounded-xl p-4
                        {{ $tarjeta['borde'] }}">

                    <p class="text-xs text-slate-400 mb-2">
                        {{ $tarjeta['titulo'] }}
                    </p>

                    <p class="text-2xl font-black {{ $tarjeta['color'] }}">

                        {{ number_format((float) $resumenHoras[$tarjeta['campo']], 2, ',', '.') }}

                        <span class="text-xs font-normal text-slate-500">
                            h
                        </span>

                    </p>

                </div>
            @endforeach

        </div>

        <!-- Información adicional -->
        <p class="text-[11px] text-slate-500">
            Las horas canceladas y en borrador se muestran
            únicamente como información adicional.
            No se suman a las horas planificadas vigentes.
            Las horas pendientes todavía no están validadas.
        </p>

    </div>
    <!-- Navegación por Pestañas -->
    <div class="flex items-center gap-2 border-b border-slate-800 text-xs">
        <button wire:click="$set('activeTab', 'contratos')"
            class="px-4 py-3 font-semibold border-b-2 transition {{ $activeTab === 'contratos' ? 'border-cyan-500 text-cyan-400' : 'border-transparent text-slate-400 hover:text-white' }}">
            Contratos Laborales ({{ $empleado->contratos->count() }})
        </button>
        <button wire:click="$set('activeTab', 'ausencias')"
            class="px-4 py-3 font-semibold border-b-2 transition {{ $activeTab === 'ausencias' ? 'border-amber-500 text-amber-400' : 'border-transparent text-slate-400 hover:text-white' }}">
            Ausencias y Bajas ({{ $empleado->ausencias->count() }})
        </button>
        <button wire:click="$set('activeTab', 'tarifas')"
            class="px-4 py-3 font-semibold border-b-2 transition {{ $activeTab === 'tarifas' ? 'border-indigo-500 text-indigo-400' : 'border-transparent text-slate-400 hover:text-white' }}">
            Tarifas de Coste ({{ $empleado->tarifasCoste->count() }})
        </button>
        <button wire:click="$set('activeTab', 'asignaciones')"
            class="px-4 py-3 font-semibold border-b-2 transition {{ $activeTab === 'asignaciones' ? 'border-emerald-500 text-emerald-400' : 'border-transparent text-slate-400 hover:text-white' }}">
            Historial de Turnos
        </button>
        <button wire:click="$set('activeTab', 'incidencias')"
            class="px-4 py-3 font-semibold border-b-2 transition {{ $activeTab === 'incidencias' ? 'border-rose-500 text-rose-400' : 'border-transparent text-slate-400 hover:text-white' }}">
            Incidencias ({{ $empleado->incidencias->count() }})
        </button>
    </div>

    <!-- Pestaña 1: Contratos -->
    @if ($activeTab === 'contratos')
        <div class="bg-slate-900/80 rounded-2xl border border-slate-800 overflow-hidden shadow-xl text-xs">
            <div class="p-4 border-b border-slate-800 flex items-center justify-between">
                <span class="font-bold text-white">Historial de Contratos</span>
                <button wire:click="$set('modalContratoOpen', true)"
                    class="text-cyan-400 hover:underline font-semibold text-xs">
                    + Registrar nuevo contrato
                </button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead
                        class="bg-slate-950/60 border-b border-slate-800 text-slate-400 uppercase tracking-wider text-[11px]">
                        <tr>
                            <th class="px-5 py-3">Tipo de Contrato</th>
                            <th class="px-5 py-3">Fecha Inicio</th>
                            <th class="px-5 py-3">Fecha Fin</th>
                            <th class="px-5 py-3">Jornada Semanal</th>
                            <th class="px-5 py-3">Estado Vigencia</th>
                            <th class="px-5 py-3">Observaciones</th>
                            <th class="px-5 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60 text-slate-300">
                        @forelse($empleado->contratos as $c)
                            @php
                                $vigente =
                                    $c->fecha_inicio <= now()->toDateString() &&
                                    ($c->fecha_fin === null || $c->fecha_fin >= now()->toDateString());
                            @endphp
                            <tr class="hover:bg-slate-800/30">
                                <td class="px-5 py-3 font-semibold text-white">{{ $c->tipo_contrato }}</td>
                                <td class="px-5 py-3">{{ $c->fecha_inicio->format('d/m/Y') }}</td>
                                <td class="px-5 py-3">
                                    {{ $c->fecha_fin ? $c->fecha_fin->format('d/m/Y') : 'Indefinido' }}</td>
                                <td class="px-5 py-3 font-medium">{{ $c->jornada_semanal_horas }} horas / semana</td>
                                <td class="px-5 py-3">
                                    @if ($vigente)
                                        <span
                                            class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">Vigente
                                            hoy</span>
                                    @else
                                        <span
                                            class="px-2 py-0.5 rounded text-[10px] font-medium bg-slate-800 text-slate-400">Histórico
                                            / Fuera de rango</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-slate-400">{{ $c->observaciones ?: '—' }}</td>
                                <td class="px-5 py-3 text-right">
                                    <button wire:click="eliminarContrato({{ $c->id }})"
                                        wire:confirm="¿Seguro que deseas eliminar este contrato del historial?"
                                        title="Eliminar contrato"
                                        class="p-1 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/20 transition inline-flex items-center">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                            </path>
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-8 text-center text-slate-400">
                                    No hay contratos registrados para este empleado.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- Pestaña 2: Ausencias -->
    @if ($activeTab === 'ausencias')
        <div class="bg-slate-900/80 rounded-2xl border border-slate-800 overflow-hidden shadow-xl text-xs">
            <div class="p-4 border-b border-slate-800 flex items-center justify-between">
                <span class="font-bold text-white">Registro de Ausencias y Bajas</span>
                <button wire:click="$set('modalAusenciaOpen', true)"
                    class="text-amber-400 hover:underline font-semibold text-xs">
                    + Añadir ausencia
                </button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead
                        class="bg-slate-950/60 border-b border-slate-800 text-slate-400 uppercase tracking-wider text-[11px]">
                        <tr>
                            <th class="px-5 py-3">Tipo</th>
                            <th class="px-5 py-3">Desde</th>
                            <th class="px-5 py-3">Hasta</th>
                            <th class="px-5 py-3">Estado</th>
                            <th class="px-5 py-3">Motivo / Observaciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60 text-slate-300">
                        @forelse($empleado->ausencias as $a)
                            <tr class="hover:bg-slate-800/30">
                                <td class="px-5 py-3 font-semibold text-white capitalize">{{ $a->tipo }}</td>
                                <td class="px-5 py-3">{{ $a->fecha_hora_inicio->format('d/m/Y H:i') }}</td>
                                <td class="px-5 py-3">{{ $a->fecha_hora_fin->format('d/m/Y H:i') }}</td>
                                <td class="px-5 py-3">
                                    @if ($a->estado === 'aprobada')
                                        <span
                                            class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">Aprobada</span>
                                    @elseif($a->estado === 'pendiente')
                                        <span
                                            class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-500/10 text-amber-400 border border-amber-500/20">Pendiente</span>
                                    @else
                                        <span
                                            class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-500/10 text-rose-400 border border-rose-500/20">Rechazada</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-slate-400">{{ $a->observaciones ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-5 py-8 text-center text-slate-400">
                                    No hay ausencias registradas.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- Pestaña 3: Tarifas de Coste -->
    @if ($activeTab === 'tarifas')
        <div class="bg-slate-900/80 rounded-2xl border border-slate-800 overflow-hidden shadow-xl text-xs">
            <div class="p-4 border-b border-slate-800 flex items-center justify-between">
                <div>
                    <span class="font-bold text-white block">Tarifas de Coste del Empleado</span>
                    <span class="text-[11px] text-slate-400">Si no tiene tarifa personalizada, se utiliza la tarifa
                        general de su categoría ({{ $empleado->categoria->nombre ?? '' }}).</span>
                </div>
                <button wire:click="$set('modalTarifaOpen', true)"
                    class="text-indigo-400 hover:underline font-semibold text-xs">
                    + Configurar tarifa personalizada
                </button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead
                        class="bg-slate-950/60 border-b border-slate-800 text-slate-400 uppercase tracking-wider text-[11px]">
                        <tr>
                            <th class="px-5 py-3">Coste / Hora</th>
                            <th class="px-5 py-3">Vigencia Desde</th>
                            <th class="px-5 py-3">Vigencia Hasta</th>
                            <th class="px-5 py-3">Observaciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60 text-slate-300">
                        @forelse($empleado->tarifasCoste as $tar)
                            <tr class="hover:bg-slate-800/30">
                                <td class="px-5 py-3 font-extrabold text-white text-sm">
                                    {{ number_format($tar->coste_hora, 2, ',', '.') }} € / h
                                </td>
                                <td class="px-5 py-3">{{ $tar->fecha_inicio->format('d/m/Y') }}</td>
                                <td class="px-5 py-3">
                                    {{ $tar->fecha_fin ? $tar->fecha_fin->format('d/m/Y') : 'Vigente indefinidamente' }}
                                </td>
                                <td class="px-5 py-3 text-slate-400">{{ $tar->observaciones ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-5 py-8 text-center text-slate-400">
                                    El empleado no tiene tarifa personalizada específica. Se aplica la tarifa base de
                                    categoría.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- Pestaña 4: Asignaciones de Turnos -->
    @if ($activeTab === 'asignaciones')
        <div class="bg-slate-900/80 rounded-2xl border border-slate-800 overflow-hidden shadow-xl text-xs">
            <div class="p-4 border-b border-slate-800">
                <span class="font-bold text-white">Historial de Turnos Asignados</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead
                        class="bg-slate-950/60 border-b border-slate-800 text-slate-400 uppercase tracking-wider text-[11px]">
                        <tr>
                            <th class="px-5 py-3">Fecha</th>
                            <th class="px-5 py-3">Horario</th>
                            <th class="px-5 py-3">Instalación y Servicio</th>
                            <th class="px-5 py-3">Estado Asignación</th>
                            <th class="px-5 py-3">Horas Reales</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60 text-slate-300">
                        @forelse($asignaciones as $asig)
                            <tr class="hover:bg-slate-800/30">
                                <td class="px-5 py-3 font-semibold text-white">
                                    {{ $asig->turno->fecha->format('d/m/Y') }}</td>
                                <td class="px-5 py-3">
                                    {{ Carbon\Carbon::parse($asig->turno->hora_inicio)->format('H:i') }} -
                                    {{ Carbon\Carbon::parse($asig->turno->hora_fin)->format('H:i') }}
                                    ({{ $asig->turno->duracionHoras() }} h)
                                </td>
                                <td class="px-5 py-3">
                                    <span
                                        class="text-white block font-medium">{{ $asig->turno->instalacion->nombre }}</span>
                                    <span
                                        class="text-[10px] text-slate-400">{{ $asig->turno->actividad->nombre }}</span>
                                </td>
                                <td class="px-5 py-3">
                                    @if ($asig->tiene_conflicto)
                                        <span
                                            class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-500/20 text-rose-300">¡Conflicto!</span>
                                    @else
                                        <span
                                            class="px-2 py-0.5 rounded text-[10px] font-semibold bg-cyan-500/10 text-cyan-400 capitalize">{{ $asig->estado }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    @if ($asig->registroHora)
                                        <span
                                            class="font-bold text-white">{{ $asig->registroHora->horas_computables }}
                                            h</span>
                                        <span
                                            class="text-[10px] block {{ $asig->registroHora->estado === 'validado' ? 'text-emerald-400' : 'text-amber-400' }}">
                                            {{ ucfirst(str_replace('_', ' ', $asig->registroHora->estado)) }}
                                        </span>
                                    @else
                                        <span class="text-slate-500">Pendiente de registro</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-5 py-8 text-center text-slate-400">
                                    No hay turnos registrados en el historial de este empleado.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($asignaciones->hasPages())
                <div class="p-4 border-t border-slate-800">
                    {{ $asignaciones->links() }}
                </div>
            @endif
        </div>
    @endif

    <!-- Pestaña 5: Incidencias -->
    @if ($activeTab === 'incidencias')
        <div class="bg-slate-900/80 rounded-2xl border border-slate-800 overflow-hidden shadow-xl text-xs">
            <div class="p-4 border-b border-slate-800">
                <span class="font-bold text-white">Incidencias Vinculadas</span>
            </div>
            <div class="divide-y divide-slate-800/60 p-4 space-y-3">
                @forelse($empleado->incidencias as $inc)
                    <div class="p-3 rounded-xl bg-slate-800/50 border border-slate-700/50">
                        <div class="flex items-center justify-between mb-1">
                            <span class="font-bold text-white">{{ $inc->titulo }}</span>
                            <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-700 text-slate-300">
                                {{ ucfirst(str_replace('_', ' ', $inc->estado)) }} ·
                                {{ $inc->fecha->format('d/m/Y') }}
                            </span>
                        </div>
                        <p class="text-slate-300">{{ $inc->descripcion }}</p>
                    </div>
                @empty
                    <p class="text-center text-slate-400 py-6">No hay incidencias vinculadas a este empleado.</p>
                @endforelse
            </div>
        </div>
    @endif

    <!-- Modal Nuevo Contrato -->
    @if ($modalContratoOpen)
        <div
            class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
            <div
                class="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6 shadow-2xl relative text-xs">
                <h3 class="text-base font-bold text-white mb-4">Registrar Nuevo Contrato</h3>
                <form wire:submit="guardarContrato" class="space-y-4">
                    <div>
                        <label class="block text-slate-300 font-medium mb-1">Tipo de Contrato *</label>
                        <input type="text" wire:model="tipo_contrato" required
                            class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
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
                            @error('fecha_fin')
                                <span class="text-rose-400 text-[10px]">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                    <div>
                        <label class="block text-slate-300 font-medium mb-1">Jornada Semanal (Horas) *</label>
                        <input type="number" step="0.5" wire:model="jornada_semanal_horas" required
                            class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
                    </div>
                    <div>
                        <label class="block text-slate-300 font-medium mb-1">Observaciones</label>
                        <textarea wire:model="observaciones_contrato" rows="2"
                            class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500"></textarea>
                    </div>
                    <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-800">
                        <button type="button" wire:click="$set('modalContratoOpen', false)"
                            class="px-4 py-2 rounded-xl bg-slate-800 text-slate-300">Cancelar</button>
                        <button type="submit"
                            class="px-4 py-2 rounded-xl bg-cyan-600 hover:bg-cyan-500 text-white font-semibold">Guardar
                            Contrato</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Modal Registrar Ausencia -->
    @if ($modalAusenciaOpen)
        <div
            class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
            <div
                class="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6 shadow-2xl relative text-xs">
                <h3 class="text-base font-bold text-white mb-4">Registrar Ausencia / Baja</h3>
                <form wire:submit="guardarAusencia" class="space-y-4">
                    <div>
                        <label class="block text-slate-300 font-medium mb-1">Tipo de Ausencia *</label>
                        <select wire:model="tipo_ausencia" required
                            class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
                            <option value="vacaciones">Vacaciones</option>
                            <option value="baja">Baja Médica / Incapacidad</option>
                            <option value="permiso">Permiso Retribuido / Deber Inexcusable</option>
                            <option value="otro">Otro</option>
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
                        <label class="block text-slate-300 font-medium mb-1">Observaciones / Motivo</label>
                        <textarea wire:model="observaciones_ausencia" rows="2"
                            class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500"></textarea>
                    </div>
                    <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-800">
                        <button type="button" wire:click="$set('modalAusenciaOpen', false)"
                            class="px-4 py-2 rounded-xl bg-slate-800 text-slate-300">Cancelar</button>
                        <button type="submit"
                            class="px-4 py-2 rounded-xl bg-amber-600 hover:bg-amber-500 text-white font-semibold">Guardar
                            y Validar Conflictos</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Modal Tarifa Coste -->
    @if ($modalTarifaOpen)
        <div
            class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
            <div
                class="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6 shadow-2xl relative text-xs">
                <h3 class="text-base font-bold text-white mb-4">Asignar Tarifa de Coste Específica</h3>
                <form wire:submit="guardarTarifa" class="space-y-4">
                    <div>
                        <label class="block text-slate-300 font-medium mb-1">Coste por Hora (€) *</label>
                        <input type="number" step="0.01" wire:model="coste_hora" required
                            class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Vigencia Desde *</label>
                            <input type="date" wire:model="tarifa_fecha_inicio" required
                                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
                        </div>
                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Vigencia Hasta (Opcional)</label>
                            <input type="date" wire:model="tarifa_fecha_fin"
                                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
                        </div>
                    </div>
                    <div>
                        <label class="block text-slate-300 font-medium mb-1">Observaciones</label>
                        <textarea wire:model="observaciones_tarifa" rows="2"
                            class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500"></textarea>
                    </div>
                    <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-800">
                        <button type="button" wire:click="$set('modalTarifaOpen', false)"
                            class="px-4 py-2 rounded-xl bg-slate-800 text-slate-300">Cancelar</button>
                        <button type="submit"
                            class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold">Guardar
                            Tarifa</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
