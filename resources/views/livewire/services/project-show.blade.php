<div class="space-y-6">
    <!-- Migas y Cabecera -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs text-slate-400 mb-1">
                <a href="{{ route('servicios.proyectos') }}" class="hover:text-cyan-400">← Volver a Proyectos</a>
                <span>/</span>
                <span class="text-slate-300">Detalle del Proyecto</span>
            </div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-black text-white">{{ $proyecto->nombre }}</h1>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 capitalize">
                    {{ $proyecto->estado }}
                </span>
            </div>
            <p class="text-xs text-slate-400 mt-1">
                Cliente: <strong class="text-slate-200">{{ $proyecto->cliente->razon_social }}</strong> · 
                Período: {{ $proyecto->fecha_inicio->format('d/m/Y') }} a {{ $proyecto->fecha_fin ? $proyecto->fecha_fin->format('d/m/Y') : 'Indefinido' }}
            </p>
        </div>

        <div class="flex items-center gap-2 text-xs">
            <a href="{{ route('servicios.necesidades', ['proyectoFiltro' => $proyecto->id]) }}"
                class="px-3.5 py-2 rounded-xl font-semibold bg-cyan-600 hover:bg-cyan-500 text-white shadow-md transition flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Gestionar Necesidades
            </a>
            <a href="{{ route('economia.informes', ['proyectoSeleccionado' => $proyecto->id]) }}"
                class="px-3.5 py-2 rounded-xl font-semibold bg-slate-800 hover:bg-slate-700 text-indigo-400 border border-slate-700 transition">
                Informe Económico Detallado →
            </a>
        </div>
    </div>

    <!-- Tarjetas de Rentabilidad y Horas de este Proyecto -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
        <div class="bg-slate-900/80 border border-slate-800 p-4 rounded-2xl">
            <span class="text-slate-400 block text-[11px]">Horas Previstas / Reales</span>
            <p class="text-lg font-bold text-white mt-1">
                {{ $informe['horas_previstas'] }} h <span class="text-slate-500">/</span> <span class="text-cyan-400">{{ $informe['horas_realizadas'] }} h</span>
            </p>
            <span class="text-[10px] text-slate-500">Planificadas vs Validadas</span>
        </div>

        <div class="bg-slate-900/80 border border-slate-800 p-4 rounded-2xl">
            <span class="text-slate-400 block text-[11px]">Coste Personal (Real)</span>
            <p class="text-lg font-bold text-white mt-1">
                {{ number_format($informe['coste_personal_real'], 2, ',', '.') }} €
            </p>
            <span class="text-[10px] text-slate-500">Previsto: {{ number_format($informe['coste_personal_previsto'], 2, ',', '.') }} €</span>
        </div>

        <div class="bg-slate-900/80 border border-slate-800 p-4 rounded-2xl">
            <span class="text-slate-400 block text-[11px]">Ingresos del Proyecto</span>
            <p class="text-lg font-bold text-emerald-400 mt-1">
                {{ number_format($informe['ingresos_totales'], 2, ',', '.') }} €
            </p>
            <span class="text-[10px] text-slate-500">Facturación certificada</span>
        </div>

        <div class="bg-slate-900/80 border border-slate-800 p-4 rounded-2xl">
            <span class="text-slate-400 block text-[11px]">Margen / Resultado Estimado</span>
            <p class="text-lg font-black {{ $informe['resultado_estimado'] >= 0 ? 'text-emerald-400' : 'text-rose-400' }} mt-1">
                {{ number_format($informe['resultado_estimado'], 2, ',', '.') }} €
            </p>
            <span class="text-[10px] text-slate-500">Ingresos - Personal - Gastos</span>
        </div>
    </div>

    <!-- Instalaciones Incluidas en el Proyecto -->
    <div class="bg-slate-900/80 rounded-2xl border border-slate-800 p-5 shadow-xl">
        <h2 class="text-sm font-bold uppercase tracking-wider text-slate-300 mb-3">
            Instalaciones Incluidas ({{ $proyecto->instalaciones->count() }})
        </h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3 text-xs">
            @forelse($proyecto->instalaciones as $inst)
                <div class="p-3 rounded-xl bg-slate-950/60 border border-slate-800">
                    <h3 class="font-bold text-white">{{ $inst->nombre }}</h3>
                    <p class="text-slate-400 text-[11px] mt-0.5">{{ $inst->direccion ?: 'Sin dirección' }}</p>
                </div>
            @empty
                <p class="text-slate-500 text-xs py-2">No se han asociado instalaciones a este proyecto.</p>
            @endforelse
        </div>
    </div>

    <!-- Necesidades de Servicio Definidas -->
    <div class="bg-slate-900/80 rounded-2xl border border-slate-800 overflow-hidden shadow-xl text-xs">
        <div class="p-4 border-b border-slate-800 flex items-center justify-between">
            <h2 class="text-sm font-bold uppercase tracking-wider text-slate-300">
                Necesidades de Servicio (Plantillas de Cobertura)
            </h2>
            <a href="{{ route('servicios.necesidades', ['proyectoFiltro' => $proyecto->id]) }}"
                class="text-xs text-cyan-400 hover:underline font-semibold">
                Gestionar y generar turnos →
            </a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="bg-slate-950/70 border-b border-slate-800 text-[11px] uppercase tracking-wider text-slate-400">
                    <tr>
                        <th class="px-5 py-3.5">Instalación</th>
                        <th class="px-5 py-3.5">Actividad y Categoría</th>
                        <th class="px-5 py-3.5">Días de Semana</th>
                        <th class="px-5 py-3.5">Horario</th>
                        <th class="px-5 py-3.5">Plazas</th>
                        <th class="px-5 py-3.5">Vigencia</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60 text-slate-300">
                    @forelse($proyecto->necesidadesServicio as $nec)
                        <tr class="hover:bg-slate-800/40">
                            <td class="px-5 py-3.5 font-bold text-white">
                                {{ $nec->instalacion->nombre }}
                            </td>
                            <td class="px-5 py-3.5">
                                <span class="font-semibold text-slate-200 block">{{ $nec->actividad->nombre }}</span>
                                <span class="text-[10px] text-cyan-400">{{ $nec->categoria->nombre }}</span>
                            </td>
                            <td class="px-5 py-3.5">
                                @php
                                    $diasMap = [1 => 'L', 2 => 'M', 3 => 'X', 4 => 'J', 5 => 'V', 6 => 'S', 7 => 'D'];
                                @endphp
                                <div class="flex gap-1">
                                    @foreach([1, 2, 3, 4, 5, 6, 7] as $d)
                                        <span class="w-5 h-5 rounded flex items-center justify-center font-bold text-[10px] {{ in_array($d, $nec->dias_semana ?? []) ? 'bg-cyan-600 text-white' : 'bg-slate-800 text-slate-500' }}">
                                            {{ $diasMap[$d] }}
                                        </span>
                                    @endforeach
                                </div>
                            </td>
                            <td class="px-5 py-3.5 whitespace-nowrap font-mono">
                                {{ substr($nec->hora_inicio, 0, 5) }} - {{ substr($nec->hora_fin, 0, 5) }}
                                @if($nec->cruza_medianoche)
                                    <span class="px-1.5 py-0.2 rounded text-[9px] bg-purple-500/20 text-purple-300 ml-1">
                                        +1 día
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 font-bold text-white">
                                {{ $nec->plazas_necesarias }} plaza(s)
                            </td>
                            <td class="px-5 py-3.5 text-slate-400 whitespace-nowrap text-[11px]">
                                {{ $nec->fecha_inicio_vigencia->format('d/m/Y') }} a {{ $nec->fecha_fin_vigencia->format('d/m/Y') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-6 text-center text-slate-400">
                                No se han definido necesidades de servicio para este proyecto.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
