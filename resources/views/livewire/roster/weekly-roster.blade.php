<div class="space-y-6">
    <!-- Cabecera y Navegación Temporal -->
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-amber-400"></span>
                <h1 class="text-xl font-black text-white">Planificación · Cuadrante Operativo de Personal</h1>
            </div>
            <p class="text-xs text-slate-400 mt-1">
                Visualización de coberturas, turnos de socorrismo y actividades, y gestión de incidencias de disponibilidad.
            </p>
        </div>

        <!-- Controles de Período y Modo de Vista -->
        <div class="flex flex-wrap items-center gap-2 text-xs">
            <div class="inline-flex rounded-xl bg-slate-900 border border-slate-800 p-1">
                <button wire:click="$set('viewMode', 'weekly')"
                    class="px-3 py-1.5 rounded-lg font-semibold transition {{ $viewMode === 'weekly' ? 'bg-cyan-600 text-white shadow' : 'text-slate-400 hover:text-white' }}">
                    Semanal
                </button>
                <button wire:click="$set('viewMode', 'monthly')"
                    class="px-3 py-1.5 rounded-lg font-semibold transition {{ $viewMode === 'monthly' ? 'bg-cyan-600 text-white shadow' : 'text-slate-400 hover:text-white' }}">
                    Mensual
                </button>
            </div>

            <div class="flex items-center gap-1 bg-slate-900 border border-slate-800 rounded-xl p-1">
                <button wire:click="{{ $viewMode === 'weekly' ? 'semanaAnterior' : 'mesAnterior' }}"
                    class="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition" title="Anterior">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                </button>
                <button wire:click="irAHoy"
                    class="px-2.5 py-1 rounded-lg text-[11px] font-semibold text-slate-300 hover:bg-slate-800 transition">
                    Hoy
                </button>
                <button wire:click="{{ $viewMode === 'weekly' ? 'semanaSiguiente' : 'mesSiguiente' }}"
                    class="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition" title="Siguiente">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </button>
            </div>

            <span class="px-3 py-1.5 rounded-xl bg-slate-800 text-white font-bold text-xs">
                @if($viewMode === 'weekly')
                    Semana del {{ $fechaInicio->format('d/m') }} al {{ $fechaFin->format('d/m/Y') }}
                @else
                    {{ $fechaInicio->isoFormat('MMMM [de] YYYY') }}
                @endif
            </span>
        </div>
    </div>

    <!-- Barra de Filtros y Métricas de Cobertura -->
    <div class="space-y-3">
        <!-- Tarjetas de Estadísticas de Cobertura -->
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-2 text-xs">
            <div class="p-3 rounded-xl bg-slate-900/80 border border-slate-800 flex items-center justify-between">
                <div>
                    <span class="text-[10px] text-slate-400 uppercase tracking-wider block font-medium">Turnos Período</span>
                    <span class="text-lg font-extrabold text-white">{{ $estadisticas['total'] }}</span>
                </div>
                <div class="w-8 h-8 rounded-lg bg-slate-800 text-slate-400 flex items-center justify-center font-bold">
                    #
                </div>
            </div>

            <div class="p-3 rounded-xl bg-slate-900/80 border border-emerald-500/20 flex items-center justify-between">
                <div>
                    <span class="text-[10px] text-emerald-400 uppercase tracking-wider block font-medium">Cubiertos</span>
                    <span class="text-lg font-extrabold text-emerald-400">{{ $estadisticas['cubiertos'] }}</span>
                </div>
                <div class="w-8 h-8 rounded-lg bg-emerald-500/10 text-emerald-400 flex items-center justify-center font-bold">
                    ✓
                </div>
            </div>

            <div class="p-3 rounded-xl bg-slate-900/80 border border-amber-500/20 flex items-center justify-between">
                <div>
                    <span class="text-[10px] text-amber-400 uppercase tracking-wider block font-medium">Parciales</span>
                    <span class="text-lg font-extrabold text-amber-400">{{ $estadisticas['parciales'] }}</span>
                </div>
                <div class="w-8 h-8 rounded-lg bg-amber-500/10 text-amber-400 flex items-center justify-center font-bold">
                    ½
                </div>
            </div>

            <div class="p-3 rounded-xl bg-slate-900/80 border border-rose-500/20 flex items-center justify-between">
                <div>
                    <span class="text-[10px] text-rose-400 uppercase tracking-wider block font-medium">Sin Cubrir</span>
                    <span class="text-lg font-extrabold text-rose-400">{{ $estadisticas['sin_cubrir'] }}</span>
                </div>
                <div class="w-8 h-8 rounded-lg bg-rose-500/10 text-rose-400 flex items-center justify-center font-bold">
                    0
                </div>
            </div>

            <div class="p-3 rounded-xl bg-slate-900/80 border border-purple-500/20 flex items-center justify-between col-span-2 sm:col-span-1">
                <div>
                    <span class="text-[10px] text-purple-400 uppercase tracking-wider block font-medium">Conflictos</span>
                    <span class="text-lg font-extrabold text-purple-400">{{ $estadisticas['conflictos'] }}</span>
                </div>
                <div class="w-8 h-8 rounded-lg bg-purple-500/10 text-purple-400 flex items-center justify-center font-bold">
                    ⚠
                </div>
            </div>
        </div>

        <!-- Filtros Combinados -->
        <div class="bg-slate-900/80 p-4 rounded-2xl border border-slate-800 grid grid-cols-2 md:grid-cols-6 gap-2 text-xs">
            <div>
                <label class="block text-slate-400 mb-1 text-[10px]">Cliente</label>
                <select wire:model.live="clienteId" class="w-full bg-slate-950 border border-slate-700 rounded-lg px-2 py-1.5 text-white">
                    <option value="">Todos</option>
                    @foreach($clientes as $c)
                        <option value="{{ $c->id }}">{{ $c->razon_social }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-slate-400 mb-1 text-[10px]">Instalación</label>
                <select wire:model.live="instalacionId" class="w-full bg-slate-950 border border-slate-700 rounded-lg px-2 py-1.5 text-white">
                    <option value="">Todas</option>
                    @foreach($instalaciones as $inst)
                        <option value="{{ $inst->id }}">{{ $inst->nombre }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-slate-400 mb-1 text-[10px]">Proyecto</label>
                <select wire:model.live="proyectoId" class="w-full bg-slate-950 border border-slate-700 rounded-lg px-2 py-1.5 text-white">
                    <option value="">Todos</option>
                    @foreach($proyectos as $p)
                        <option value="{{ $p->id }}">{{ $p->nombre }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-slate-400 mb-1 text-[10px]">Empleado</label>
                <select wire:model.live="empleadoId" class="w-full bg-slate-950 border border-slate-700 rounded-lg px-2 py-1.5 text-white">
                    <option value="">Todos</option>
                    @foreach($empleados as $e)
                        <option value="{{ $e->id }}">{{ $e->nombre_completo }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-slate-400 mb-1 text-[10px]">Categoría</label>
                <select wire:model.live="categoriaId" class="w-full bg-slate-950 border border-slate-700 rounded-lg px-2 py-1.5 text-white">
                    <option value="">Todas</option>
                    @foreach($categorias as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->nombre }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-slate-400 mb-1 text-[10px]">Estado Cobertura</label>
                <select wire:model.live="estadoCobertura" class="w-full bg-slate-950 border border-slate-700 rounded-lg px-2 py-1.5 text-white">
                    <option value="">Todos los estados</option>
                    <option value="cubierto">Cubierto (100%)</option>
                    <option value="parcialmente_cubierto">Parcialmente Cubierto</option>
                    <option value="sin_cubrir">Sin Cubrir (0%)</option>
                    <option value="con_conflicto">Con Conflicto / Baja</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Vista Semanal en Cuadrante (7 Columnas de Lunes a Domingo) -->
    @if($viewMode === 'weekly')
        <div class="grid grid-cols-1 md:grid-cols-7 gap-3">
            @foreach($diasPeriodo as $dia)
                @php
                    $fechaStr = $dia->toDateString();
                    $turnosDia = $turnosPorFecha->get($fechaStr, collect());
                    $esHoy = $fechaStr === now('Europe/Madrid')->toDateString();
                @endphp
                <div class="flex flex-col bg-slate-900/60 rounded-2xl border {{ $esHoy ? 'border-cyan-500/50 ring-1 ring-cyan-500/30' : 'border-slate-800' }} min-h-[350px]">
                    <!-- Cabecera de Columna por Día -->
                    <div class="p-3 border-b border-slate-800 text-center {{ $esHoy ? 'bg-cyan-950/40 text-cyan-300' : 'bg-slate-950/60 text-slate-300' }} rounded-t-2xl">
                        <span class="text-[11px] font-bold uppercase tracking-wider block">
                            {{ $dia->isoFormat('dddd') }}
                        </span>
                        <span class="text-sm font-extrabold block mt-0.5">
                            {{ $dia->format('d/m') }}
                        </span>
                        <span class="text-[10px] text-slate-500 block">
                            {{ $turnosDia->count() }} turno(s)
                        </span>
                    </div>

                    <!-- Lista de Turnos del Día -->
                    <div class="p-2 space-y-2 flex-1 overflow-y-auto">
                        @forelse($turnosDia as $t)
                            @php
                                $estadoCob = $t->estadoCobertura();
                                $bordeColor = match($estadoCob) {
                                    'cubierto' => 'border-emerald-500/40 bg-emerald-950/10',
                                    'parcialmente_cubierto' => 'border-amber-500/40 bg-amber-950/10',
                                    'sin_cubrir' => 'border-rose-500/40 bg-rose-950/10',
                                    'con_conflicto' => 'border-purple-500/60 bg-purple-950/20 ring-1 ring-purple-500/30',
                                    default => 'border-slate-800 bg-slate-950/40',
                                };
                            @endphp
                            <div wire:click="abrirModalTurno({{ $t->id }})"
                                class="p-2.5 rounded-xl border {{ $bordeColor }} hover:border-cyan-400 cursor-pointer transition shadow text-xs space-y-1.5 group">
                                
                                <div class="flex items-center justify-between text-[11px]">
                                    <span class="font-bold text-white group-hover:text-cyan-300 transition">
                                        {{ Carbon\Carbon::parse($t->hora_inicio)->format('H:i') }}-{{ Carbon\Carbon::parse($t->hora_fin)->format('H:i') }}
                                    </span>
                                    <span class="text-[10px] font-semibold px-1.5 py-0.2 rounded
                                        @if($estadoCob === 'cubierto') bg-emerald-500/20 text-emerald-300
                                        @elseif($estadoCob === 'parcialmente_cubierto') bg-amber-500/20 text-amber-300
                                        @elseif($estadoCob === 'sin_cubrir') bg-rose-500/20 text-rose-300
                                        @else bg-purple-500/30 text-purple-300 @endif">
                                        {{ $t->plazasAsignadas() }}/{{ $t->plazas_necesarias }}
                                    </span>
                                </div>

                                <div>
                                    <span class="text-[11px] font-medium text-slate-200 block truncate">
                                        {{ $t->instalacion->nombre }}
                                    </span>
                                    <span class="text-[10px] text-slate-400 block truncate">
                                        {{ $t->actividad->nombre }} · {{ $t->categoria->codigo }}
                                    </span>
                                </div>

                                <!-- Empleados Asignados -->
                                <div class="pt-1 border-t border-slate-800/60 space-y-1">
                                    @forelse($t->asignacionesActivas as $asig)
                                        <div class="flex items-center justify-between text-[10px] {{ $asig->tiene_conflicto ? 'text-purple-300 font-bold' : 'text-slate-300' }}">
                                            <span class="truncate">
                                                {{ $asig->tiene_conflicto ? '⚠ ' : '• ' }}{{ $asig->empleado->nombre_completo }}
                                            </span>
                                            @if($asig->tiene_conflicto)
                                                <span class="text-[9px] text-purple-400">Conflicto</span>
                                            @endif
                                        </div>
                                    @empty
                                        <span class="text-[10px] text-rose-400 italic block font-semibold">
                                            Sin cubrir (Click para asignar)
                                        </span>
                                    @endforelse
                                </div>
                            </div>
                        @empty
                            <div class="h-24 flex items-center justify-center text-slate-600 text-[11px] italic">
                                Sin turnos
                            </div>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <!-- Vista Mensual Compacta -->
        <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-7 gap-2">
            @foreach($diasPeriodo as $dia)
                @php
                    $fechaStr = $dia->toDateString();
                    $turnosDia = $turnosPorFecha->get($fechaStr, collect());
                    $cubiertosDia = $turnosDia->filter(fn($t) => $t->estadoCobertura() === 'cubierto')->count();
                    $parcialesDia = $turnosDia->filter(fn($t) => $t->estadoCobertura() === 'parcialmente_cubierto')->count();
                    $sinCubrirDia = $turnosDia->filter(fn($t) => $t->estadoCobertura() === 'sin_cubrir')->count();
                    $conflictosDia = $turnosDia->filter(fn($t) => $t->estadoCobertura() === 'con_conflicto')->count();
                @endphp
                <div class="p-3 bg-slate-900/70 border border-slate-800 rounded-xl text-xs space-y-1">
                    <div class="flex items-center justify-between">
                        <span class="font-bold text-white">{{ $dia->format('d') }}</span>
                        <span class="text-[10px] text-slate-500 uppercase">{{ $dia->isoFormat('ddd') }}</span>
                    </div>
                    @if($turnosDia->isNotEmpty())
                        <div class="text-[10px] space-y-0.5 pt-1">
                            <span class="block text-slate-300 font-semibold">{{ $turnosDia->count() }} turnos</span>
                            @if($cubiertosDia > 0)
                                <span class="text-emerald-400 block">✓ {{ $cubiertosDia }} cubiertos</span>
                            @endif
                            @if($sinCubrirDia > 0)
                                <span class="text-rose-400 block font-bold">✕ {{ $sinCubrirDia }} sin cubrir</span>
                            @endif
                            @if($conflictosDia > 0)
                                <span class="text-purple-400 block font-bold">⚠ {{ $conflictosDia }} conflictos</span>
                            @endif
                        </div>
                    @else
                        <span class="text-[10px] text-slate-600 italic block pt-1">—</span>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    <!-- Modal Interactivo de Asignación y Resolución de Conflictos -->
    @if($modalTurnoOpen && $turnoSeleccionado)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-lg w-full p-6 shadow-2xl relative text-xs space-y-4">
                
                <!-- Encabezado del Turno -->
                <div class="border-b border-slate-800 pb-3 flex items-start justify-between">
                    <div>
                        <span class="text-[10px] font-semibold text-cyan-400 uppercase tracking-wider block">
                            {{ $turnoSeleccionado->proyecto->nombre }}
                        </span>
                        <h3 class="text-base font-bold text-white mt-0.5">
                            {{ $turnoSeleccionado->instalacion->nombre }}
                        </h3>
                        <p class="text-slate-400 text-xs">
                            {{ $turnoSeleccionado->actividad->nombre }} · Categoría requerida: <strong class="text-slate-200">{{ $turnoSeleccionado->categoria->nombre }}</strong>
                        </p>
                    </div>
                    <button wire:click="$set('modalTurnoOpen', false)" class="text-slate-400 hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <!-- Detalles Horarios y Plazas -->
                <div class="grid grid-cols-3 gap-2 p-3 rounded-xl bg-slate-950/70 border border-slate-800 text-center">
                    <div>
                        <span class="text-slate-400 text-[10px] block">Fecha</span>
                        <strong class="text-white">{{ $turnoSeleccionado->fecha->format('d/m/Y') }}</strong>
                    </div>
                    <div>
                        <span class="text-slate-400 text-[10px] block">Horario</span>
                        <strong class="text-white font-mono">
                            {{ Carbon\Carbon::parse($turnoSeleccionado->hora_inicio)->format('H:i') }} - {{ Carbon\Carbon::parse($turnoSeleccionado->hora_fin)->format('H:i') }}
                        </strong>
                        <span class="text-[10px] text-slate-500 block">({{ $turnoSeleccionado->duracionHoras() }} h)</span>
                    </div>
                    <div>
                        <span class="text-slate-400 text-[10px] block">Cobertura</span>
                        <strong class="text-white">
                            {{ $turnoSeleccionado->plazasAsignadas() }} de {{ $turnoSeleccionado->plazas_necesarias }}
                        </strong>
                        <span class="text-[10px] font-semibold block
                            @if($turnoSeleccionado->estadoCobertura() === 'cubierto') text-emerald-400
                            @elseif($turnoSeleccionado->estadoCobertura() === 'sin_cubrir') text-rose-400
                            @else text-amber-400 @endif">
                            {{ ucfirst(str_replace('_', ' ', $turnoSeleccionado->estadoCobertura())) }}
                        </span>
                    </div>
                </div>

                <!-- Empleados Actualmente Asignados -->
                <div class="space-y-2">
                    <span class="text-[11px] font-bold text-slate-300 uppercase tracking-wider block">
                        Empleados Asignados
                    </span>
                    @if($turnoSeleccionado->asignacionesActivas->isEmpty())
                        <p class="text-rose-400 italic py-2">No hay ningún empleado asignado a este turno.</p>
                    @else
                        <div class="space-y-1.5">
                            @foreach($turnoSeleccionado->asignacionesActivas as $asig)
                                <div class="p-2.5 rounded-xl bg-slate-950 border {{ $asig->tiene_conflicto ? 'border-rose-500/50 bg-rose-950/20' : 'border-slate-800' }} flex items-center justify-between">
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="font-bold text-white">{{ $asig->empleado->nombre_completo }}</span>
                                            <span class="text-[10px] text-slate-400">({{ $asig->empleado->categoria->nombre ?? '' }})</span>
                                        </div>
                                        @if($asig->tiene_conflicto)
                                            <span class="text-[10px] text-rose-400 font-semibold block mt-0.5">
                                                ⚠ Conflicto: {{ $asig->motivo_conflicto }}
                                            </span>
                                        @endif
                                    </div>
                                    <button wire:click="desasignarEmpleado({{ $asig->id }})"
                                        class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-rose-900/40 text-rose-400 text-[11px] font-semibold transition">
                                        Eliminar
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <!-- Formulario para Asignar Nuevo Empleado con Validación en Servidor -->
                @if($turnoSeleccionado->plazasDisponibles() > 0)
                    <div class="pt-3 border-t border-slate-800 space-y-3">
                        <span class="text-[11px] font-bold text-cyan-400 uppercase tracking-wider block">
                            + Asignar Nuevo Empleado (Plazas disponibles: {{ $turnoSeleccionado->plazasDisponibles() }})
                        </span>

                        @if(!empty($erroresValidacion))
                            <div class="p-3 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300 space-y-1">
                                <span class="font-bold block">No se pudo realizar la asignación:</span>
                                <ul class="list-disc list-inside text-[11px] space-y-0.5">
                                    @foreach($erroresValidacion as $err)
                                        <li>{{ $err }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <div class="flex gap-2">
                            <select wire:model="nuevoEmpleadoId"
                                class="flex-1 bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
                                <option value="">Seleccionar empleado compatible...</option>
                                @foreach($empleados as $emp)
                                    <option value="{{ $emp->id }}">
                                        {{ $emp->nombre_completo }} ({{ $emp->categoria->nombre ?? '' }})
                                    </option>
                                @endforeach
                            </select>
                            <button wire:click="asignarEmpleado"
                                class="px-4 py-2 rounded-xl bg-cyan-600 hover:bg-cyan-500 text-white font-semibold shrink-0 transition">
                                Validar y Asignar
                            </button>
                        </div>
                    </div>
                @else
                    <div class="p-3 rounded-xl bg-slate-950/80 border border-slate-800 text-slate-400 text-center">
                        Este turno tiene cubierto el 100% de sus plazas ({{ $turnoSeleccionado->plazas_necesarias }} de {{ $turnoSeleccionado->plazas_necesarias }}).
                    </div>
                @endif

                <div class="pt-3 border-t border-slate-800 flex justify-end">
                    <button wire:click="$set('modalTurnoOpen', false)"
                        class="px-4 py-2 rounded-xl bg-slate-800 text-slate-300 hover:bg-slate-700 transition">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
