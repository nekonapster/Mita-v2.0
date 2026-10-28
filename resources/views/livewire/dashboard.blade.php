<div class="space-y-6">
    <!-- Cabecera de Bienvenida y Resumen Rápido -->
    <div class="bg-gradient-to-r from-slate-900 via-slate-800 to-cyan-950 p-6 rounded-2xl border border-slate-700/60 shadow-xl relative overflow-hidden">
        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2">
                    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-cyan-500/20 text-cyan-300 border border-cyan-500/30">
                        {{ auth()->user()->roles->pluck('display_name')->join(', ') }}
                    </span>
                    <span class="text-xs text-slate-400">Zona horaria: Europe/Madrid</span>
                </div>
                <h1 class="text-2xl font-black text-white mt-2">
                    Hola, {{ auth()->user()->name }}
                </h1>
                <p class="text-xs text-slate-300 mt-1 max-w-2xl">
                    Bienvenido a <strong>MiTa</strong>. Gestiona la cobertura de socorrismo y actividades, planificación de turnos, recursos humanos y control financiero interno.
                </p>
            </div>

            <!-- Accesos rápidos -->
            <div class="flex flex-wrap items-center gap-2 shrink-0">
                @hasanyrole(['admin', 'planner'])
                    <a href="{{ route('planificacion.cuadrante') }}" class="px-3.5 py-2 rounded-xl text-xs font-semibold bg-cyan-600 hover:bg-cyan-500 text-white shadow-md shadow-cyan-950 transition flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        Abrir Cuadrante
                    </a>
                @endhasanyrole

                @hasanyrole(['admin', 'accountant', 'planner'])
                    <a href="{{ route('horas.validacion') }}" class="px-3.5 py-2 rounded-xl text-xs font-semibold bg-indigo-600 hover:bg-indigo-500 text-white shadow-md transition flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Validar Horas ({{ $registrosPendientesValidar }})
                    </a>
                @endhasanyrole

                @hasanyrole(['employee', 'admin'])
                    <a href="{{ route('portal.empleado') }}" class="px-3.5 py-2 rounded-xl text-xs font-semibold bg-sky-600 hover:bg-sky-500 text-white shadow-md transition flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Mis Turnos
                    </a>
                @endhasanyrole
            </div>
        </div>
    </div>

    <!-- Alertas Urgentes: Conflictos en Cuadrante -->
    @if($conflictosPendientes->isNotEmpty())
        <div class="bg-rose-950/30 border border-rose-500/40 rounded-2xl p-4 shadow-lg">
            <div class="flex items-start gap-3">
                <div class="w-8 h-8 rounded-xl bg-rose-500/20 text-rose-400 flex items-center justify-center shrink-0 mt-0.5">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                </div>
                <div class="flex-1">
                    <div class="flex items-center justify-between">
                        <h2 class="text-sm font-bold text-rose-300">
                            Atención: Hay {{ $conflictosPendientes->count() }} conflicto(s) pendiente(s) de resolver en la planificación
                        </h2>
                        <a href="{{ route('planificacion.cuadrante') }}" class="text-xs text-rose-400 hover:text-rose-200 underline font-medium">
                            Ir al cuadrante →
                        </a>
                    </div>
                    <p class="text-xs text-slate-300 mt-1">
                        Se han aprobado ausencias que interfieren con turnos ya asignados o existen solapamientos que requieren reasignación.
                    </p>
                    <div class="mt-3 grid grid-cols-1 md:grid-cols-2 gap-2 text-xs">
                        @foreach($conflictosPendientes as $conf)
                            <div class="p-2.5 rounded-xl bg-slate-900/80 border border-rose-500/30 flex items-center justify-between">
                                <div>
                                    <span class="font-semibold text-white">{{ $conf->empleado->nombre_completo }}</span>
                                    <span class="text-slate-400 block text-[11px]">
                                        {{ $conf->turno->instalacion->nombre }} · {{ $conf->turno->fecha->format('d/m/Y') }} ({{ Carbon\Carbon::parse($conf->turno->hora_inicio)->format('H:i') }}-{{ Carbon\Carbon::parse($conf->turno->hora_fin)->format('H:i') }})
                                    </span>
                                    <span class="text-rose-400 text-[10px]">{{ $conf->motivo_conflicto }}</span>
                                </div>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-500/20 text-rose-300">Conflicto</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Bloque 1: Cobertura Semanal de Servicios (Planificador / Admin) -->
    @hasanyrole(['admin', 'planner', 'rrhh'])
    <div>
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-sm font-bold uppercase tracking-wider text-slate-300 flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-amber-400"></span>
                Planificación Semanal (Semana actual)
            </h2>
            <a href="{{ route('planificacion.cuadrante') }}" class="text-xs text-cyan-400 hover:underline">
                Ver cuadrante detallado
            </a>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
            <div class="bg-slate-900/80 border border-slate-800 p-4 rounded-2xl">
                <span class="text-[11px] font-medium text-slate-400 uppercase tracking-wider">Total Turnos</span>
                <p class="text-2xl font-extrabold text-white mt-1">{{ $metricasPlanificacion['total'] }}</p>
                <span class="text-[10px] text-slate-500">Programados esta semana</span>
            </div>

            <div class="bg-slate-900/80 border border-slate-800 p-4 rounded-2xl">
                <span class="text-[11px] font-medium text-emerald-400 uppercase tracking-wider">Cubiertos 100%</span>
                <p class="text-2xl font-extrabold text-emerald-400 mt-1">{{ $metricasPlanificacion['cubiertos'] }}</p>
                <span class="text-[10px] text-slate-500">Plazas completas</span>
            </div>

            <div class="bg-slate-900/80 border border-slate-800 p-4 rounded-2xl">
                <span class="text-[11px] font-medium text-amber-400 uppercase tracking-wider">Parciales</span>
                <p class="text-2xl font-extrabold text-amber-400 mt-1">{{ $metricasPlanificacion['parciales'] }}</p>
                <span class="text-[10px] text-slate-500">Falta alguna plaza</span>
            </div>

            <div class="bg-slate-900/80 border border-slate-800 p-4 rounded-2xl">
                <span class="text-[11px] font-medium text-rose-400 uppercase tracking-wider">Sin Cubrir</span>
                <p class="text-2xl font-extrabold text-rose-400 mt-1">{{ $metricasPlanificacion['sin_cubrir'] }}</p>
                <span class="text-[10px] text-slate-500">0 empleados asignados</span>
            </div>

            <div class="bg-slate-900/80 border border-slate-800 p-4 rounded-2xl">
                <span class="text-[11px] font-medium text-purple-400 uppercase tracking-wider">Conflictos</span>
                <p class="text-2xl font-extrabold text-purple-400 mt-1">{{ $metricasPlanificacion['conflictos'] }}</p>
                <span class="text-[10px] text-slate-500">Bajas o solapes</span>
            </div>
        </div>
    </div>
    @endhasanyrole

    <!-- Bloque 2: Recursos Humanos y Laboral -->
    @hasanyrole(['admin', 'rrhh'])
    <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
        <div class="bg-slate-900/80 border border-slate-800 p-4 rounded-2xl flex items-center justify-between">
            <div>
                <span class="text-[11px] font-medium text-slate-400 uppercase tracking-wider">Empleados Activos</span>
                <p class="text-2xl font-extrabold text-white mt-1">{{ $empleadosActivos }}</p>
                <a href="{{ route('rrhh.empleados') }}" class="text-[10px] text-cyan-400 hover:underline">Ver plantilla →</a>
            </div>
            <div class="w-10 h-10 rounded-xl bg-cyan-500/10 text-cyan-400 flex items-center justify-center">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
            </div>
        </div>

        <div class="bg-slate-900/80 border border-slate-800 p-4 rounded-2xl flex items-center justify-between">
            <div>
                <span class="text-[11px] font-medium text-slate-400 uppercase tracking-wider">Contratos Vigentes</span>
                <p class="text-2xl font-extrabold text-white mt-1">{{ $contratosVigentes }}</p>
                <span class="text-[10px] text-slate-500">En vigor hoy</span>
            </div>
            <div class="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
            </div>
        </div>

        <div class="bg-slate-900/80 border border-slate-800 p-4 rounded-2xl flex items-center justify-between">
            <div>
                <span class="text-[11px] font-medium text-amber-400 uppercase tracking-wider">Ausencias Pendientes</span>
                <p class="text-2xl font-extrabold text-amber-400 mt-1">{{ $ausenciasPendientes }}</p>
                <a href="{{ route('rrhh.ausencias') }}" class="text-[10px] text-cyan-400 hover:underline">Revisar solicitudes →</a>
            </div>
            <div class="w-10 h-10 rounded-xl bg-amber-500/10 text-amber-400 flex items-center justify-center">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
            </div>
        </div>

        <div class="bg-slate-900/80 border border-slate-800 p-4 rounded-2xl flex items-center justify-between">
            <div>
                <span class="text-[11px] font-medium text-rose-400 uppercase tracking-wider">Incidencias Abiertas</span>
                <p class="text-2xl font-extrabold text-rose-400 mt-1">{{ $incidenciasAbiertas }}</p>
                <a href="{{ route('rrhh.incidencias') }}" class="text-[10px] text-cyan-400 hover:underline">Gestionar incidencias →</a>
            </div>
            <div class="w-10 h-10 rounded-xl bg-rose-500/10 text-rose-400 flex items-center justify-center">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
            </div>
        </div>
    </div>
    @endhasanyrole

    <!-- Bloque 3: Resumen Económico Interno (Contable / Admin) -->
    @hasanyrole(['admin', 'accountant'])
    @if($informeEconomico && $proyectoActivo)
        <div class="bg-slate-900/80 border border-slate-800 p-5 rounded-2xl space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-[10px] uppercase font-bold tracking-wider text-indigo-400">Control Financiero Interno</span>
                    <h2 class="text-base font-bold text-white">{{ $proyectoActivo->nombre }}</h2>
                </div>
                <a href="{{ route('economia.informes') }}" class="text-xs text-indigo-400 hover:underline font-medium">
                    Ver informe económico completo →
                </a>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-xs">
                <div class="p-3 rounded-xl bg-slate-800/60 border border-slate-700/50">
                    <span class="text-slate-400 block text-[11px]">Ingresos Registrados</span>
                    <span class="text-lg font-bold text-emerald-400 mt-1 block">
                        {{ number_format($informeEconomico['ingresos_totales'], 2, ',', '.') }} €
                    </span>
                </div>

                <div class="p-3 rounded-xl bg-slate-800/60 border border-slate-700/50">
                    <span class="text-slate-400 block text-[11px]">Coste Personal Real (Validadas)</span>
                    <span class="text-lg font-bold text-white mt-1 block">
                        {{ number_format($informeEconomico['coste_personal_real'], 2, ',', '.') }} €
                    </span>
                    <span class="text-[10px] text-slate-500">{{ $informeEconomico['horas_realizadas'] }} h validadas</span>
                </div>

                <div class="p-3 rounded-xl bg-slate-800/60 border border-slate-700/50">
                    <span class="text-slate-400 block text-[11px]">Gastos Adicionales</span>
                    <span class="text-lg font-bold text-amber-400 mt-1 block">
                        {{ number_format($informeEconomico['gastos_adicionales'], 2, ',', '.') }} €
                    </span>
                    <span class="text-[10px] text-slate-500">Materiales y consumibles</span>
                </div>

                <div class="p-3 rounded-xl bg-slate-800/60 border border-slate-700/50">
                    <span class="text-slate-400 block text-[11px]">Margen / Resultado Estimado</span>
                    <span class="text-lg font-black {{ $informeEconomico['resultado_estimado'] >= 0 ? 'text-emerald-400' : 'text-rose-400' }} mt-1 block">
                        {{ number_format($informeEconomico['resultado_estimado'], 2, ',', '.') }} €
                    </span>
                    <span class="text-[10px] text-slate-500">Ingresos - Personal - Gastos</span>
                </div>
            </div>
        </div>
    @endif
    @endhasanyrole

    <!-- Bloque 4: Sección para Empleado (si tiene usuario vinculado a empleado) -->
    @if(auth()->user()->employee_id)
        <div class="bg-slate-900/80 border border-slate-800 p-5 rounded-2xl space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-[10px] uppercase font-bold tracking-wider text-sky-400">Portal del Empleado</span>
                    <h2 class="text-base font-bold text-white">Mis Próximos Turnos Programados</h2>
                </div>
                <a href="{{ route('portal.empleado') }}" class="text-xs text-sky-400 hover:underline font-medium">
                    Gestionar mis horas e imputaciones →
                </a>
            </div>

            @if($misProximosTurnos->isEmpty())
                <p class="text-xs text-slate-400 py-3">No tienes turnos programados en los próximos días.</p>
            @else
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    @foreach($misProximosTurnos as $t)
                        <div class="p-3 rounded-xl bg-slate-800/60 border border-slate-700/50 text-xs">
                            <div class="flex items-center justify-between mb-1">
                                <span class="font-bold text-white">{{ $t->fecha->format('d/m/Y') }}</span>
                                <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-sky-500/20 text-sky-300">
                                    {{ Carbon\Carbon::parse($t->hora_inicio)->format('H:i') }} - {{ Carbon\Carbon::parse($t->hora_fin)->format('H:i') }}
                                </span>
                            </div>
                            <p class="text-slate-300 font-medium">{{ $t->instalacion->nombre }}</p>
                            <p class="text-[11px] text-slate-400">{{ $t->actividad->nombre }}</p>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif
</div>
