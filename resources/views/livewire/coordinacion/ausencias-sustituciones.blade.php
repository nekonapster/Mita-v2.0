<div class="space-y-6">

    <div>
        <h1 class="text-xl font-black text-white">
            Coordinación Operativa · Ausencias y Sustituciones
        </h1>
        <p class="text-xs text-slate-400 mt-1">
            Registro de ausencias y cobertura por compañeros.
        </p>
    </div>

    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 max-w-3xl space-y-5">

        <h2 class="text-base font-bold text-white">
            Registrar ausencia de empleado
        </h2>



        <!-- Datos de la ausencia -->
        <div class="space-y-4">

            <!-- Primera fila: 3 columnas -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

                <div>
                    <label class="block text-xs text-slate-300 mb-1">Empleado ausente *</label>
                    <select wire:model="empleadoAusenteId"
                        class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white text-xs">
                        <option value="">Seleccionar empleado...</option>
                        @foreach($empleados as $empleado)
                        <option value="{{ $empleado->id }}">{{ $empleado->nombre_completo }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs text-slate-300 mb-1">Tipo de ausencia *</label>
                    <select wire:model="tipoAusencia"
                        class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white text-xs">
                        <option value="vacaciones">Vacaciones</option>
                        <option value="baja">Baja médica</option>
                        <option value="permiso">Permiso</option>
                        <option value="otro">Otros motivos</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs text-slate-300 mb-1">Modalidad de pago *</label>
                    <select wire:model="modalidadPago"
                        class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white text-xs">
                        <option value="empresa">Paga ATAAA</option>
                        <option value="empresa_descuento_horas">ATAAA paga y descuenta horas al sustituido</option>
                        <option value="monitor_sustituido">Paga el monitor sustituido</option>
                    </select>
                </div>

            </div>

            <!-- Segunda fila: 2 columnas -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                <div>
                    <label class="block text-xs text-slate-300 mb-1">Fecha y hora de inicio *</label>
                    <input type="datetime-local" wire:model.live="fechaHoraInicio"
                        class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white text-xs">
                </div>

                <div>
                    <label class="block text-xs text-slate-300 mb-1">Fecha y hora de finalización *</label>
                    <input type="datetime-local" wire:model.live="fechaHoraFin"
                        class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white text-xs">
                </div>

            </div>

            <!-- Tercera fila: ancho completo -->
            <div>
                <label class="block text-xs text-slate-300 mb-1">Motivo / Observaciones</label>
                <textarea wire:model="observaciones" rows="2"
                    class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white text-xs"
                    placeholder="Anotaciones adicionales..."></textarea>
            </div>

        </div>



        <div class="border-t border-slate-800 pt-5 space-y-4">

            <div class="flex items-center justify-between">
                <h3 class="text-sm font-bold text-white">
                    Cobertura de la ausencia
                </h3>

                <span class="text-xs text-slate-400">
                    {{ count($sustitutos) }} tramo(s)
                </span>
            </div>

            <p class="text-xs text-slate-400">
                Añadí tantos compañeros como necesites.
                Cada uno puede cubrir una franja horaria diferente.
            </p>

            @foreach($sustitutos as $index => $tramo)
            <div wire:key="sustituto-{{ $tramo['id'] }}"
                class="p-4 rounded-xl bg-slate-950 border border-slate-700 space-y-3">

                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-cyan-400">
                        Sustituto {{ $index + 1 }}
                    </span>

                    <button type="button" wire:click="eliminarSustituto({{ $tramo['id'] }})"
                        class="text-xs text-rose-400 hover:text-rose-300">
                        Eliminar
                    </button>
                </div>

                <div>
                    <label class="block text-xs text-slate-300 mb-1">
                        Empleado sustituto
                    </label>


                    <select wire:model="sustitutos.{{ $index }}.empleado_id"
                        class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white text-xs focus:border-cyan-500">

                        <option value="">Seleccionar compañero...</option>

                        @foreach($empleados as $empleado)
                        <option value="{{ $empleado->id }}">
                            {{ $empleado->nombre_completo }}
                            ({{ $empleado->categoria->nombre ?? 'Sin categoría' }})
                        </option>
                        @endforeach

                    </select>

                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs text-slate-300 mb-1">
                            Inicio
                        </label>

                        <input type="datetime-local" wire:model.live="sustitutos.{{ $index }}.inicio"
                            class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white text-xs">
                    </div>

                    <div>
                        <label class="block text-xs text-slate-300 mb-1">
                            Fin
                        </label>

                        <input type="datetime-local" wire:model.live="sustitutos.{{ $index }}.fin"
                            class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white text-xs">
                    </div>
                </div>
            </div>
            @endforeach

            <button type="button" wire:click="agregarSustituto"
                class="w-full py-2.5 rounded-xl border border-dashed border-slate-600 text-slate-300 hover:border-cyan-500 hover:text-cyan-400 text-xs font-semibold transition">
                + Añadir sustituto
            </button>

            @php
            $cobertura = $this->calcularCobertura();
            @endphp

            <div class="flex gap-6 text-sm">
                <span class="text-emerald-400">
                  CUBIERTAS = {{ $cobertura['cubiertas'] }} hr. 
                </span>
                <span> - </span>
                <span class="text-amber-400">
                   SIN CUBRIR = {{ $cobertura['sin_cubrir'] }} hr. 
                </span>
            </div>


            @if ($errors->any())
            <div class="rounded-xl border border-red-500/40 bg-red-500/10 p-4">
                <p class="text-sm font-semibold text-red-300 mb-2">
                    No se pudo guardar. Revisá los siguientes datos:
                </p>

                <ul class="list-disc list-inside space-y-1 text-xs text-red-200">
                    @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            @endif

            @if (session()->has('sustitucion_guardada'))
            <div class="rounded-xl border border-emerald-500/40 bg-emerald-500/10 p-4 text-sm text-emerald-300"
                role="status">
                {{ session('sustitucion_guardada') }}
            </div>
            @endif


            <div class="flex justify-end border-t border-slate-700 pt-5 mt-5">
                <button type="button" wire:click="guardar" wire:loading.attr="disabled" wire:target="guardar"
                    @disabled($guardadoCorrectamente)
                    class="px-6 py-3 rounded-xl bg-cyan-600 hover:bg-cyan-500 text-white text-sm font-semibold disabled:opacity-50 disabled:cursor-not-allowed">

                    <span wire:loading.remove wire:target="guardar">
                        {{ $guardadoCorrectamente
                        ? 'Ausencia registrada'
                        : 'Guardar ausencia y sustituciones' }}
                    </span>

                    <span wire:loading wire:target="guardar">
                        Guardando...
                    </span>
                </button>
            </div>


            @if(count($sustitutos) === 0)
            <p class="text-xs text-amber-400">
                Sin sustitutos: la ausencia permanecerá sin cobertura.
            </p>
            @endif

        </div>
    </div>
</div>