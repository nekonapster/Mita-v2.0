<div class="space-y-6">
    <!-- Cabecera -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-black text-white flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-indigo-400"></span>
                Control Económico · Informes de Rentabilidad y Costes
            </h1>
            <p class="text-xs text-slate-400 mt-1">
                Balance interno por proyecto e instalación: horas previstas/reales, costes laborales directos, ingresos y gastos adicionales.
            </p>
        </div>

        <div class="flex items-center gap-2 text-xs">
            <a href="{{ route('economia.tarifas') }}"
                class="px-3 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-indigo-400 font-semibold border border-slate-700 transition">
                Configurar Tarifas / Hora
            </a>
            <a href="{{ route('economia.movimientos') }}"
                class="px-3 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-emerald-400 font-semibold border border-slate-700 transition">
                + Movimientos (Ingresos/Gastos)
            </a>
        </div>
    </div>

    <!-- Barra de Filtros del Informe -->
    <div class="bg-slate-900/80 p-4 rounded-2xl border border-slate-800 grid grid-cols-1 sm:grid-cols-4 gap-3 text-xs">
        <div>
            <label class="block text-slate-400 mb-1 font-medium">Proyecto Seleccionado *</label>
            <select wire:model.live="proyectoSeleccionado"
                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500 font-semibold">
                @foreach($proyectos as $p)
                    <option value="{{ $p->id }}">{{ $p->nombre }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-slate-400 mb-1 font-medium">Instalación (Opcional)</label>
            <select wire:model.live="instalacionSeleccionada"
                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
                <option value="">Todas las instalaciones del proyecto</option>
                @foreach($instalacionesDelProyecto as $inst)
                    <option value="{{ $inst->id }}">{{ $inst->nombre }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-slate-400 mb-1 font-medium">Fecha Desde</label>
            <input type="date" wire:model.live="fechaDesde"
                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
        </div>

        <div>
            <label class="block text-slate-400 mb-1 font-medium">Fecha Hasta</label>
            <input type="date" wire:model.live="fechaHasta"
                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
        </div>
    </div>

    <!-- Alertas si falta alguna tarifa en los cálculos -->
    @if($informe)
        @if($informe['coste_previsto_incompleto'] || $informe['coste_real_incompleto'])
            <div class="p-4 rounded-2xl bg-amber-500/10 border border-amber-500/30 text-amber-300 text-xs flex items-start gap-3">
                <div class="w-6 h-6 rounded-lg bg-amber-500/20 text-amber-400 flex items-center justify-center shrink-0 mt-0.5">
                    ⚠
                </div>
                <div>
                    <strong class="font-bold block text-sm">Advertencia: Cálculo de Coste Incompleto</strong>
                    <p class="mt-0.5 text-slate-300">
                        Uno o más empleados asignados no tienen una tarifa horaria de coste vigente para la fecha del turno ni en su ficha personal ni en su categoría. El coste reflejado no incluye las horas de esos empleados hasta que se configure su tarifa.
                    </p>
                    <a href="{{ route('economia.tarifas') }}" class="underline text-amber-400 hover:text-white mt-1.5 inline-block font-medium">
                        Ir a configurar tarifas de coste →
                    </a>
                </div>
            </div>
        @endif

        <!-- Bloque Principal de Métricas Financieras -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 text-xs">
            <div class="bg-slate-900/80 border border-slate-800 p-5 rounded-2xl">
                <span class="text-slate-400 block text-[11px] uppercase tracking-wider">Horas del Proyecto</span>
                <div class="mt-2 flex items-baseline justify-between">
                    <div>
                        <span class="text-2xl font-black text-white">{{ $informe['horas_realizadas'] }} h</span>
                        <span class="text-[10px] text-emerald-400 block font-medium">Validadas reales</span>
                    </div>
                    <div class="text-right">
                        <span class="text-base font-bold text-slate-400">{{ $informe['horas_previstas'] }} h</span>
                        <span class="text-[10px] text-slate-500 block">Previstas</span>
                    </div>
                </div>
            </div>

            <div class="bg-slate-900/80 border border-slate-800 p-5 rounded-2xl">
                <span class="text-slate-400 block text-[11px] uppercase tracking-wider">Coste Personal Directo</span>
                <div class="mt-2 flex items-baseline justify-between">
                    <div>
                        <span class="text-2xl font-black text-white">
                            {{ number_format($informe['coste_personal_real'], 2, ',', '.') }} €
                        </span>
                        <span class="text-[10px] text-emerald-400 block font-medium">Sobre validadas</span>
                    </div>
                    <div class="text-right">
                        <span class="text-sm font-bold text-slate-400">
                            {{ number_format($informe['coste_personal_previsto'], 2, ',', '.') }} €
                        </span>
                        <span class="text-[10px] text-slate-500 block">Previsto</span>
                    </div>
                </div>
            </div>

            <div class="bg-slate-900/80 border border-slate-800 p-5 rounded-2xl">
                <span class="text-slate-400 block text-[11px] uppercase tracking-wider">Ingresos y Gastos Adic.</span>
                <div class="mt-2 flex items-baseline justify-between">
                    <div>
                        <span class="text-2xl font-black text-emerald-400">
                            {{ number_format($informe['ingresos_totales'], 2, ',', '.') }} €
                        </span>
                        <span class="text-[10px] text-slate-400 block">Ingresos facturados</span>
                    </div>
                    <div class="text-right">
                        <span class="text-base font-bold text-amber-400">
                            {{ number_format($informe['gastos_adicionales'], 2, ',', '.') }} €
                        </span>
                        <span class="text-[10px] text-slate-500 block">Materiales/Otros</span>
                    </div>
                </div>
            </div>

            <div class="bg-slate-900/80 border border-slate-800 p-5 rounded-2xl">
                <span class="text-slate-400 block text-[11px] uppercase tracking-wider">Resultado Interno Estimado</span>
                <div class="mt-2">
                    <span class="text-2xl font-black {{ $informe['resultado_estimado'] >= 0 ? 'text-emerald-400' : 'text-rose-400' }} block">
                        {{ number_format($informe['resultado_estimado'], 2, ',', '.') }} €
                    </span>
                    <span class="text-[10px] text-slate-400 block mt-0.5">
                        Margen operativo estimado
                    </span>
                </div>
            </div>
        </div>

        <!-- Explicación de Fórmulas y Metodología de Cálculo -->
        <div class="bg-slate-900/80 rounded-2xl border border-slate-800 p-5 shadow-xl space-y-3 text-xs">
            <h2 class="text-sm font-bold uppercase tracking-wider text-slate-300 flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-cyan-400"></span>
                Metodología y Fuentes de Datos Empleadas en los Cálculos
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-slate-300 pt-1">
                @foreach($informe['desglose_explicativo'] as $explicacion)
                    <div class="p-3 rounded-xl bg-slate-950/60 border border-slate-800/80 flex items-start gap-2">
                        <span class="text-cyan-400 font-bold">•</span>
                        <p class="text-[11px] leading-relaxed">{{ $explicacion }}</p>
                    </div>
                @endforeach
            </div>
            <div class="p-3 rounded-xl bg-indigo-950/30 border border-indigo-500/20 text-indigo-300 text-[11px]">
                <strong>Nota de control:</strong> Los gastos adicionales (materiales didácticos, consumibles, productos químicos, botiquines) están rigurosamente separados del coste de personal para evitar doble imputación en los resultados.
            </div>
        </div>
    @endif
</div>
