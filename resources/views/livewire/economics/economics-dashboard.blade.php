<div class="space-y-6">
    <!-- Cabecera -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-black text-white flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-indigo-400"></span>
                Control Económico · Informes de Rentabilidad y Costes
            </h1>
            <p class="text-xs text-slate-400 mt-1">
                Balance interno por proyecto e instalación: horas previstas/reales, costes laborales directos, ingresos
                y gastos adicionales.
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

    {{-- Estado del informe económico --}}
    @if($informe)
    @php
    $tipoInforme = $informe['tipo_informe'] ?? 'dinamico';
    @endphp

    <div class="rounded-2xl border p-4 text-sm
        @if($tipoInforme === 'consolidado')
            bg-emerald-500/10 border-emerald-500/30 text-emerald-300
        @elseif($tipoInforme === 'mixto')
            bg-violet-500/10 border-violet-500/30 text-violet-300
        @else
            bg-cyan-500/10 border-cyan-500/30 text-cyan-300
        @endif
    ">
        @if($tipoInforme === 'consolidado')
        <strong>🔒 Informe consolidado</strong>
        <p class="mt-1 text-xs">
            Los importes corresponden al cierre económico oficial
            del período seleccionado.
        </p>

        @elseif($tipoInforme === 'mixto')
        <strong>🔄 Informe mixto</strong>
        <p class="mt-1 text-xs">
            Este informe combina períodos cerrados con
            períodos calculados dinámicamente.
        </p>

        @else
        <strong>📊 Informe dinámico</strong>
        <p class="mt-1 text-xs">
            Los importes se calculan utilizando los datos
            económicos actuales.
        </p>
        @endif
    </div>

    {{-- Advertencia cuando se consultan datos dinámicos de períodos cerrados --}}
    @if($informe['advertencia_cierre'] ?? false)
    <div class="rounded-2xl border border-amber-500/30 bg-amber-500/10 p-4 text-amber-300">
        <strong class="text-sm">⚠️ Advertencia sobre el cierre económico</strong>

        <p class="mt-1 text-xs text-slate-300">
            Esta consulta incluye datos de un período económicamente cerrado.
            Los importes calculados dinámicamente no representan
            necesariamente el cierre económico oficial.
        </p>
    </div>
    @endif
    @endif

    {{-- Gestión de cierres económicos --}}
    @if(auth()->user()?->isAdmin() ||
    auth()->user()?->roles()->where('name', \App\Models\Role::ACCOUNTANT)->exists())

    <div class="bg-slate-900/80 border border-indigo-500/30 rounded-2xl p-5 space-y-4">

        <div>
            <h2 class="text-sm font-bold text-white">
                🔒 Gestión de cierres económicos
            </h2>

            <p class="text-xs text-slate-400 mt-1">
                Consolidá los resultados económicos de un proyecto
                para un mes determinado.
            </p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end">

            <div>
                <label class="block text-xs text-slate-400 mb-1">
                    Año del cierre
                </label>

                <input type="number" min="2000" max="9999" wire:model="anioCierre"
                    class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white">

                @error('anioCierre')
                <span class="text-xs text-rose-400">{{ $message }}</span>
                @enderror
            </div>

            <div>
                <label class="block text-xs text-slate-400 mb-1">
                    Mes del cierre
                </label>

                <select wire:model="mesCierre"
                    class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white">
                    @foreach(range(1, 12) as $mes)
                    <option value="{{ $mes }}">
                        {{ \Carbon\Carbon::create(2026, $mes, 1)->locale('es')->monthName }}
                    </option>
                    @endforeach
                </select>

                @error('mesCierre')
                <span class="text-xs text-rose-400">{{ $message }}</span>
                @enderror
            </div>

            <button type="button" wire:click="prepararCierre" wire:loading.attr="disabled" wire:target="prepararCierre"
                @disabled(!$proyecto)
                class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white text-xs font-bold transition">
                Preparar cierre económico
            </button>

        </div>

        <p class="text-xs text-amber-300">
            ⚠️ Los cierres son definitivos y no pueden modificarse
            mediante las operaciones habituales de MiTa.
        </p>

        @error('proyectoSeleccionado')
        <span class="text-xs text-rose-400">{{ $message }}</span>
        @enderror

    </div>
    @endif

    {{-- Historial de cierres económicos --}}
    @if($proyecto && $historialCierres->isNotEmpty())

    <section class="bg-slate-900/80 border border-slate-800 rounded-2xl p-5 space-y-4">

        <h2 class="text-sm font-bold text-white">
            🔒 Historial de cierres económicos
        </h2>

        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">

                <thead class="text-xs text-slate-400 border-b border-slate-700">
                    <tr>
                        <th class="py-3 pr-4">Período</th>
                        <th class="py-3 pr-4">Responsable</th>
                        <th class="py-3 pr-4">Fecha del cierre</th>
                        <th class="py-3 pr-4 text-right">Ingresos consolidados</th>
                        <th class="py-3 pr-4 text-right">Coste personal consolidado</th>
                        <th class="py-3 pr-4 text-right">Gastos consolidados</th>
                        <th class="py-3 pr-4 text-right">Resultado consolidado</th>
                        <th class="py-3">Estado</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-800">

                    @foreach($historialCierres as $cierre)
                    <tr>

                        <td class="py-3 text-white">
                            {{ \Carbon\Carbon::create(
                            $cierre->anio,
                            $cierre->mes,
                            1
                            )->locale('es')->monthName }}

                            {{ $cierre->anio }}
                        </td>

                        <td class="py-3 text-slate-300">
                            {{ $cierre->usuarioCierre?->name ?? 'Usuario no disponible' }}
                        </td>

                        <td class="py-3 text-slate-300">
                            {{ $cierre->cerrado_en->format('d/m/Y H:i') }}
                        </td>

                        @php
                        $datos = $cierre->datos_consolidados ?? [];
                        @endphp

                        <td class="py-3 pr-4 text-right text-emerald-400">
                            {{ number_format((float) ($datos['ingresos_totales'] ?? 0), 2, ',', '.') }} €
                        </td>

                        <td class="py-3 pr-4 text-right text-slate-300">
                            {{ number_format((float) ($datos['coste_personal_real'] ?? 0), 2, ',', '.') }} €
                        </td>

                        <td class="py-3 pr-4 text-right text-amber-400">
                            {{ number_format((float) ($datos['gastos_adicionales'] ?? 0), 2, ',', '.') }} €
                        </td>

                        <td class="py-3 pr-4 text-right font-bold
    {{ ($datos['resultado_estimado'] ?? 0) >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                            {{ number_format((float) ($datos['resultado_estimado'] ?? 0), 2, ',', '.') }} €
                        </td>

                        <td class="py-3">
                            <span class="text-emerald-400 font-semibold">
                                🔒 Cerrado
                            </span>
                        </td>
                    </tr>
                    @endforeach

                </tbody>
            </table>
        </div>
    </section>

    @endif

    <!-- Alertas si falta alguna tarifa en los cálculos -->
    @if($informe)
    @if($informe['coste_previsto_incompleto'] || $informe['coste_real_incompleto'])
    <div
        class="p-4 rounded-2xl bg-amber-500/10 border border-amber-500/30 text-amber-300 text-xs flex items-start gap-3">
        <div class="w-6 h-6 rounded-lg bg-amber-500/20 text-amber-400 flex items-center justify-center shrink-0 mt-0.5">
            ⚠
        </div>
        <div>
            <strong class="font-bold block text-sm">Advertencia: Cálculo de Coste Incompleto</strong>
            <p class="mt-0.5 text-slate-300">
                Uno o más empleados asignados no tienen una tarifa horaria de coste vigente para la fecha del turno ni
                en su ficha personal ni en su categoría. El coste reflejado no incluye las horas de esos empleados hasta
                que se configure su tarifa.
            </p>
            <a href="{{ route('economia.tarifas') }}"
                class="underline text-amber-400 hover:text-white mt-1.5 inline-block font-medium">
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
                <span
                    class="text-2xl font-black {{ $informe['resultado_estimado'] >= 0 ? 'text-emerald-400' : 'text-rose-400' }} block">
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
            <strong>Nota de control:</strong> Los gastos adicionales (materiales didácticos, consumibles, productos
            químicos, botiquines) están rigurosamente separados del coste de personal para evitar doble imputación en
            los resultados.
        </div>
    </div>
    @endif
    {{-- Modal de confirmación del cierre económico --}}
    @if($modalCierreOpen && $proyecto)
    @php
    $cierrePendiente = $tokenCierre
    ? \Illuminate\Support\Facades\Cache::get('mita.cierre.' . $tokenCierre)
    : null;
    $mesConfirmado = $cierrePendiente['mes'] ?? null;
    $anioConfirmado = $cierrePendiente['anio'] ?? null;
    $proyectoConfirmado = isset($cierrePendiente['proyecto_id'])
    ? $proyectos->firstWhere('id', $cierrePendiente['proyecto_id'])
    : null;
    @endphp

    @if(($proyectoConfirmado && $mesConfirmado && $anioConfirmado) || $errors->has('periodo'))
    <div class="fixed inset-0 z-50 flex items-center justify-center
                    bg-slate-950/80 backdrop-blur-sm p-4">

        <div class="bg-slate-900 border border-amber-500/30
                        rounded-2xl w-full max-w-md p-6 shadow-2xl space-y-5">

            <div>
                <h3 class="text-lg font-bold text-white">
                    🔒 Confirmar cierre económico
                </h3>

                <p class="text-xs text-slate-400 mt-2">
                    Estás a punto de consolidar los resultados económicos
                    del siguiente período:
                </p>
            </div>

            @if($proyectoConfirmado && $mesConfirmado && $anioConfirmado)
            <div class="bg-slate-950 border border-slate-800
                                        rounded-xl p-4 space-y-3 text-sm">

                <div>
                    <span class="text-slate-400">Proyecto:</span>
                    <strong class="text-white block mt-1">
                        {{ $proyectoConfirmado->nombre }}
                    </strong>
                </div>

                <div>
                    <span class="text-slate-400">Período:</span>
                    <strong class="text-white block mt-1">
                        {{ \Carbon\Carbon::create(
                        2026,
                        $mesConfirmado,
                        1
                        )->locale('es')->monthName }}
                        {{ $anioConfirmado }}
                    </strong>
                </div>
            </div>
            @endif

            <div class="bg-amber-500/10 border border-amber-500/30
                            rounded-xl p-4 text-xs text-amber-300">
                ⚠️ <strong>Atención:</strong>
                una vez confirmado, los importes económicos quedarán
                consolidados y no podrán modificarse mediante las
                operaciones habituales de MiTa.
            </div>
            {{-- Errores al ejecutar el cierre económico --}}
            @error('periodo')
            <div role="alert" class="bg-rose-500/10 border border-rose-500/30
               rounded-xl p-4 space-y-2">
                <p class="text-sm font-bold text-rose-400">
                    No se pudo realizar el cierre económico
                </p>

                <p class="text-xs text-rose-300">
                    {{ $message }}
                </p>

                <p class="text-xs text-slate-400">
                    Cancelá esta operación y prepará nuevamente
                    el cierre después de corregir el problema.
                </p>
            </div>
            @enderror
            <div class="flex justify-end gap-3">

                <button type="button" wire:click="cancelarCierre" wire:loading.attr="disabled" class="px-4 py-2 rounded-xl bg-slate-800
                               hover:bg-slate-700 text-slate-300 text-xs">
                    Cancelar cierre
                </button>

                <button type="button" wire:click="ejecutarCierre" wire:loading.attr="disabled"
                    wire:target="ejecutarCierre" @disabled($errors->has('periodo') || !$proyectoConfirmado)
                    class="px-4 py-2 rounded-xl bg-amber-600
                    hover:bg-amber-500 text-white text-xs font-bold">
                    Confirmar cierre económico

                </button>

            </div>
        </div>
    </div>
    @endif
    @endif
</div>