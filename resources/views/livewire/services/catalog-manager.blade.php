<div class="space-y-6">
    <div>
        <h1 class="text-xl font-black text-white flex items-center gap-2">
            <span class="w-2.5 h-2.5 rounded-full bg-cyan-400"></span>
            Catálogos · Categorías Profesionales y Actividades
        </h1>
        <p class="text-xs text-slate-400 mt-1">
            Catálogos editables de perfiles laborales (socorrista, monitor, coordinador) y actividades ofrecidas a clientes.
        </p>
    </div>

    @if ($feedbackMessage)
        <div class="p-3 bg-emerald-500/10 border border-emerald-500/30 rounded-xl text-emerald-400 text-xs flex items-center justify-between">
            <span>{{ $feedbackMessage }}</span>
            <button wire:click="$set('feedbackMessage', null)" class="text-emerald-400 hover:text-emerald-300 font-bold ml-2">✕</button>
        </div>
    @endif

    @if ($feedbackError)
        <div class="p-3 bg-rose-500/10 border border-rose-500/30 rounded-xl text-rose-400 text-xs flex items-center justify-between">
            <span>{{ $feedbackError }}</span>
            <button wire:click="$set('feedbackError', null)" class="text-rose-400 hover:text-rose-300 font-bold ml-2">✕</button>
        </div>
    @endif

    @if (session()->has('success'))
        <div class="p-3 bg-emerald-500/10 border border-emerald-500/30 rounded-xl text-emerald-400 text-xs">
            {{ session('success') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 text-xs">
        <!-- Bloque 1: Categorías Profesionales -->
        <div class="bg-slate-900/80 rounded-2xl border border-slate-800 p-5 space-y-4 shadow-xl">
            <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                <h2 class="text-sm font-bold text-white uppercase tracking-wider">Categorías Profesionales</h2>
                <span class="text-slate-400 text-[11px]">{{ $categorias->count() }} registradas</span>
            </div>

            <!-- Formulario añadir categoría -->
            <form wire:submit="guardarCategoria" class="bg-slate-950/60 p-3 rounded-xl border border-slate-800 space-y-3">
                <span class="font-bold text-slate-300 block text-[11px]">+ Añadir Nueva Categoría</span>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <input type="text" wire:model="catNombre" placeholder="Nombre (ej: Monitor Padel)" required
                            class="w-full bg-slate-900 border border-slate-700 rounded-lg px-2.5 py-1.5 text-white focus:border-cyan-500">
                    </div>
                    <div>
                        <input type="text" wire:model="catCodigo" placeholder="Código (ej: MON-PAD)" required
                            class="w-full bg-slate-900 border border-slate-700 rounded-lg px-2.5 py-1.5 text-white focus:border-cyan-500 uppercase">
                    </div>
                </div>
                <div class="flex gap-2">
                    <input type="text" wire:model="catDescripcion" placeholder="Descripción breve..."
                        class="flex-1 bg-slate-900 border border-slate-700 rounded-lg px-2.5 py-1.5 text-white focus:border-cyan-500">
                    <button type="submit" class="px-3 py-1.5 rounded-lg bg-cyan-600 hover:bg-cyan-500 text-white font-semibold shrink-0">
                        Añadir
                    </button>
                </div>
            </form>

            <!-- Listado de Categorías -->
            <div class="divide-y divide-slate-800/80">
                @foreach($categorias as $cat)
                    <div class="py-2.5 flex items-center justify-between">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-white">{{ $cat->nombre }}</span>
                                <span class="px-1.5 py-0.2 rounded font-mono text-[10px] bg-slate-800 text-cyan-300 border border-slate-700">
                                    {{ $cat->codigo }}
                                </span>
                            </div>
                            <span class="text-[11px] text-slate-400 block">{{ $cat->descripcion ?: 'Sin descripción' }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-[10px] text-slate-500">{{ $cat->empleados_count }} empleado(s)</span>
                            <button wire:click="toggleEstadoCategoria({{ $cat->id }})"
                                class="px-2 py-0.5 rounded text-[10px] font-semibold {{ $cat->activo ? 'bg-emerald-500/10 text-emerald-400' : 'bg-slate-800 text-slate-500' }}">
                                {{ $cat->activo ? 'Activa' : 'Inactiva' }}
                            </button>
                            <button wire:click="eliminarCategoria({{ $cat->id }})"
                                wire:confirm="¿Seguro que deseas eliminar la categoría '{{ $cat->nombre }}'?"
                                title="Eliminar categoría"
                                class="px-2 py-0.5 rounded text-[10px] font-semibold bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/20 transition">
                                Eliminar
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Bloque 2: Catálogo de Actividades -->
        <div class="bg-slate-900/80 rounded-2xl border border-slate-800 p-5 space-y-4 shadow-xl">
            <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                <h2 class="text-sm font-bold text-white uppercase tracking-wider">Actividades y Servicios</h2>
                <span class="text-slate-400 text-[11px]">{{ $actividades->count() }} registradas</span>
            </div>

            <!-- Formulario añadir actividad -->
            <form wire:submit="guardarActividad" class="bg-slate-950/60 p-3 rounded-xl border border-slate-800 space-y-3">
                <span class="font-bold text-slate-300 block text-[11px]">+ Añadir Nueva Actividad</span>
                <div>
                    <input type="text" wire:model="actNombre" placeholder="Nombre de la actividad (ej: Natación Terapéutica)" required
                        class="w-full bg-slate-900 border border-slate-700 rounded-lg px-2.5 py-1.5 text-white focus:border-cyan-500">
                </div>
                <div class="flex gap-2">
                    <input type="text" wire:model="actDescripcion" placeholder="Descripción breve..."
                        class="flex-1 bg-slate-900 border border-slate-700 rounded-lg px-2.5 py-1.5 text-white focus:border-cyan-500">
                    <button type="submit" class="px-3 py-1.5 rounded-lg bg-cyan-600 hover:bg-cyan-500 text-white font-semibold shrink-0">
                        Añadir
                    </button>
                </div>
            </form>

            <!-- Listado de Actividades -->
            <div class="divide-y divide-slate-800/80">
                @foreach($actividades as $act)
                    <div class="py-2.5 flex items-center justify-between">
                        <div>
                            <span class="font-bold text-white block">{{ $act->nombre }}</span>
                            <span class="text-[11px] text-slate-400">{{ $act->descripcion ?: 'Sin descripción' }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-[10px] text-slate-500">{{ $act->turnos_count }} turno(s)</span>
                            <button wire:click="toggleEstadoActividad({{ $act->id }})"
                                class="px-2 py-0.5 rounded text-[10px] font-semibold {{ $act->activo ? 'bg-emerald-500/10 text-emerald-400' : 'bg-slate-800 text-slate-500' }}">
                                {{ $act->activo ? 'Activa' : 'Inactiva' }}
                            </button>
                            <button wire:click="eliminarActividad({{ $act->id }})"
                                wire:confirm="¿Seguro que deseas eliminar la actividad '{{ $act->nombre }}'?"
                                title="Eliminar actividad"
                                class="px-2 py-0.5 rounded text-[10px] font-semibold bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/20 transition">
                                Eliminar
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
