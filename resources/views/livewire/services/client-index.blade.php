<div class="space-y-6">
    <!-- Cabecera -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-black text-white flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-cyan-400"></span>
                Servicios · Clientes e Instalaciones
            </h1>
            <p class="text-xs text-slate-400 mt-1">
                Entidades clientes, polideportivos, piscinas cubiertas, vasos de verano y dependencias técnicas.
            </p>
        </div>

        <button wire:click="abrirModalCliente()"
            class="px-4 py-2 rounded-xl text-xs font-semibold bg-cyan-600 hover:bg-cyan-500 text-white shadow-md transition flex items-center gap-1.5 shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            Nuevo Cliente
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
            <label class="block text-slate-400 mb-1 font-medium">Buscar cliente</label>
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Razón social, CIF o persona de contacto..."
                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
        </div>
        <div>
            <label class="block text-slate-400 mb-1 font-medium">Estado</label>
            <select wire:model.live="estadoFiltro"
                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
                <option value="">Todos</option>
                <option value="1">Activos</option>
                <option value="0">Inactivos</option>
            </select>
        </div>
    </div>

    <!-- Listado con Instalaciones Expandidas -->
    <div class="space-y-4">
        @forelse($clientes as $cli)
            <div class="bg-slate-900/80 rounded-2xl border border-slate-800 p-5 shadow-xl space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-800/80 pb-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-base font-bold text-white">{{ $cli->razon_social }}</h2>
                            @if($cli->nif_cif)
                                <span class="px-2 py-0.5 rounded font-mono text-[10px] bg-slate-800 text-slate-400 border border-slate-700">
                                    {{ $cli->nif_cif }}
                                </span>
                            @endif
                            @if($cli->activo)
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                    Activo
                                </span>
                            @else
                                <span class="px-2 py-0.5 rounded text-[10px] font-medium bg-slate-800 text-slate-500">
                                    Inactivo
                                </span>
                            @endif
                        </div>
                        <div class="text-xs text-slate-400 mt-1 flex flex-wrap gap-4">
                            @if($cli->persona_contacto)
                                <span>Contacto: <strong class="text-slate-300">{{ $cli->persona_contacto }}</strong></span>
                            @endif
                            @if($cli->email)
                                <span>{{ $cli->email }}</span>
                            @endif
                            @if($cli->telefono)
                                <span>Tel: {{ $cli->telefono }}</span>
                            @endif
                        </div>
                    </div>

                    <div class="flex items-center gap-2 text-xs shrink-0">
                        <button wire:click="abrirModalInstalacion({{ $cli->id }})"
                            class="px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-cyan-400 border border-slate-700 transition font-medium">
                            + Añadir Instalación
                        </button>
                        <button wire:click="abrirModalCliente({{ $cli->id }})"
                            class="px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 font-medium transition">
                            Editar
                        </button>
                        <button wire:click="confirmarEliminarCliente({{ $cli->id }})"
                            class="px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-rose-900/40 text-rose-400 font-medium transition">
                            Eliminar
                        </button>
                    </div>
                </div>

                <!-- Subtabla de Instalaciones pertenecientes al cliente -->
                <div>
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block mb-2">
                        Instalaciones Vinculadas ({{ $cli->instalaciones->count() }})
                    </span>

                    @if($cli->instalaciones->isEmpty())
                        <div class="p-4 rounded-xl bg-slate-950/40 border border-dashed border-slate-800 text-center text-xs text-slate-500">
                            No hay instalaciones registradas para este cliente todavía.
                        </div>
                    @else
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                            @foreach($cli->instalaciones as $inst)
                                <div class="p-3 rounded-xl bg-slate-950/60 border border-slate-800/80 text-xs">
                                    <div class="flex items-center justify-between mb-1">
                                        <h3 class="font-bold text-white">{{ $inst->nombre }}</h3>
                                        <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                                    </div>
                                    @if($inst->direccion)
                                        <p class="text-slate-400 text-[11px]">{{ $inst->direccion }}</p>
                                    @endif
                                    @if($inst->persona_contacto)
                                        <p class="text-slate-400 text-[11px] mt-1">
                                            Contacto: {{ $inst->persona_contacto }} {{ $inst->telefono ? '('.$inst->telefono.')' : '' }}
                                        </p>
                                    @endif
                                    <div class="mt-2 pt-2 border-t border-slate-800/80 flex justify-end">
                                        <button wire:click="confirmarEliminarInstalacion({{ $inst->id }})"
                                            class="text-[10px] text-rose-400 hover:text-rose-300 font-medium transition flex items-center gap-1">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                            Eliminar
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <div class="p-8 text-center bg-slate-900/60 rounded-2xl border border-slate-800 text-slate-400 text-xs">
                No se encontraron clientes registrados.
            </div>
        @endforelse

        @if($clientes->hasPages())
            <div class="p-4 border-t border-slate-800">
                {{ $clientes->links() }}
            </div>
        @endif
    </div>

    <!-- Modal Cliente -->
    @if($modalClienteOpen)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-lg w-full p-6 shadow-2xl relative text-xs">
                <h3 class="text-base font-bold text-white mb-4">
                    {{ $clienteId ? 'Editar Cliente' : 'Nuevo Cliente' }}
                </h3>
                <form wire:submit="guardarCliente" class="space-y-4">
                    <div>
                        <label class="block text-slate-300 font-medium mb-1">Razón Social o Nombre *</label>
                        <input type="text" wire:model="razon_social" required
                            class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-slate-300 font-medium mb-1">NIF / CIF</label>
                            <input type="text" wire:model="nif_cif"
                                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
                        </div>
                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Persona de Contacto</label>
                            <input type="text" wire:model="persona_contacto"
                                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Email</label>
                            <input type="email" wire:model="email"
                                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
                        </div>
                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Teléfono</label>
                            <input type="text" wire:model="telefono"
                                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
                        </div>
                    </div>

                    <div>
                        <label class="block text-slate-300 font-medium mb-1">Dirección Completa</label>
                        <input type="text" wire:model="direccion"
                            class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
                    </div>

                    <div>
                        <label class="block text-slate-300 font-medium mb-1">Observaciones</label>
                        <textarea wire:model="observaciones" rows="2"
                            class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500"></textarea>
                    </div>

                    <div class="flex items-center gap-2">
                        <input type="checkbox" id="cliActivo" wire:model="activo"
                            class="rounded border-slate-700 bg-slate-950 text-cyan-600 focus:ring-cyan-500">
                        <label for="cliActivo" class="text-slate-300 font-medium">Cliente activo</label>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-800">
                        <button type="button" wire:click="$set('modalClienteOpen', false)"
                            class="px-4 py-2 rounded-xl bg-slate-800 text-slate-300">Cancelar</button>
                        <button type="submit"
                            class="px-4 py-2 rounded-xl bg-cyan-600 hover:bg-cyan-500 text-white font-semibold">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Modal Instalación -->
    @if($modalInstalacionOpen)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6 shadow-2xl relative text-xs">
                <h3 class="text-base font-bold text-white mb-4">Añadir Nueva Instalación</h3>
                <form wire:submit="guardarInstalacion" class="space-y-4">
                    <div>
                        <label class="block text-slate-300 font-medium mb-1">Nombre de la Instalación *</label>
                        <input type="text" wire:model="instalacion_nombre" required placeholder="Ej: Polideportivo Zaburdón"
                            class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
                    </div>

                    <div>
                        <label class="block text-slate-300 font-medium mb-1">Dirección</label>
                        <input type="text" wire:model="instalacion_direccion"
                            class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Persona de Contacto</label>
                            <input type="text" wire:model="instalacion_contacto"
                                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
                        </div>
                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Teléfono</label>
                            <input type="text" wire:model="instalacion_telefono"
                                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500">
                        </div>
                    </div>

                    <div>
                        <label class="block text-slate-300 font-medium mb-1">Observaciones</label>
                        <textarea wire:model="instalacion_observaciones" rows="2"
                            class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white focus:border-cyan-500"></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-800">
                        <button type="button" wire:click="$set('modalInstalacionOpen', false)"
                            class="px-4 py-2 rounded-xl bg-slate-800 text-slate-300">Cancelar</button>
                        <button type="submit"
                            class="px-4 py-2 rounded-xl bg-cyan-600 hover:bg-cyan-500 text-white font-semibold">Guardar Instalación</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Modal Confirmar Eliminación Cliente -->
    @if($modalDeleteClienteOpen)
        <div class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-md overflow-hidden shadow-2xl p-6 text-xs">
                <div class="flex items-center gap-3 text-rose-400 mb-3">
                    <div class="w-10 h-10 rounded-xl bg-rose-500/10 border border-rose-500/20 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                    </div>
                    <div>
                        <h4 class="text-sm font-bold text-white">¿Eliminar cliente?</h4>
                        <p class="text-[11px] text-slate-400">Entidad cliente: {{ $clienteToDeleteNombre }}</p>
                    </div>
                </div>

                <div class="bg-slate-950/60 p-3.5 rounded-xl border border-slate-800 mb-4 space-y-2">
                    @if($clienteTieneRegistros)
                        <div class="p-2.5 rounded-lg bg-amber-500/10 border border-amber-500/20 text-amber-300 text-[11px]">
                            ⚠ Este cliente cuenta con instalaciones o proyectos vinculados. Para evitar la pérdida de históricos de facturación y turnos, <strong>se marcará como Inactivo</strong> en lugar de purgar sus datos.
                        </div>
                    @else
                        <p class="text-slate-300 text-[11px]">
                            Este cliente no tiene instalaciones ni proyectos. Se eliminará permanentemente de la base de datos.
                        </p>
                    @endif
                </div>

                <div class="flex items-center justify-end gap-3">
                    <button type="button" wire:click="$set('modalDeleteClienteOpen', false)"
                        class="px-4 py-2 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800 transition">
                        Cancelar
                    </button>
                    <button type="button" wire:click="eliminarCliente"
                        class="px-5 py-2 rounded-xl bg-rose-600 hover:bg-rose-500 text-white font-semibold shadow-lg shadow-rose-900/30 transition">
                        {{ $clienteTieneRegistros ? 'Desactivar Cliente' : 'Eliminar Cliente' }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal Confirmar Eliminación Instalación -->
    @if($modalDeleteInstalacionOpen)
        <div class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-md overflow-hidden shadow-2xl p-6 text-xs">
                <div class="flex items-center gap-3 text-rose-400 mb-3">
                    <div class="w-10 h-10 rounded-xl bg-rose-500/10 border border-rose-500/20 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                    </div>
                    <div>
                        <h4 class="text-sm font-bold text-white">¿Eliminar instalación?</h4>
                        <p class="text-[11px] text-slate-400">{{ $instalacionToDeleteNombre }}</p>
                    </div>
                </div>

                <div class="bg-slate-950/60 p-3.5 rounded-xl border border-slate-800 mb-4 space-y-2">
                    @if($instalacionTieneRegistros)
                        <div class="p-2.5 rounded-lg bg-amber-500/10 border border-amber-500/20 text-amber-300 text-[11px]">
                            ⚠ Esta instalación cuenta con necesidades de servicio o turnos generados. Para no romper los cuadrantes, <strong>se marcará como Inactiva</strong>.
                        </div>
                    @else
                        <p class="text-slate-300 text-[11px]">
                            Esta instalación no tiene turnos ni necesidades. Se eliminará permanentemente.
                        </p>
                    @endif
                </div>

                <div class="flex items-center justify-end gap-3">
                    <button type="button" wire:click="$set('modalDeleteInstalacionOpen', false)"
                        class="px-4 py-2 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800 transition">
                        Cancelar
                    </button>
                    <button type="button" wire:click="eliminarInstalacion"
                        class="px-5 py-2 rounded-xl bg-rose-600 hover:bg-rose-500 text-white font-semibold shadow-lg shadow-rose-900/30 transition">
                        {{ $instalacionTieneRegistros ? 'Desactivar Instalación' : 'Eliminar Registro' }}
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
