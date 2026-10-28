<!DOCTYPE html>
<html lang="es" class="h-full bg-slate-900 text-slate-100">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'MiTa' }} - Plataforma de Gestión de Servicios</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        [x-cloak] { display: none !important; }
    </style>
    <script>
        if (localStorage.getItem('mita_theme') === 'light') {
            document.documentElement.classList.add('light');
        } else {
            document.documentElement.classList.remove('light');
        }
    </script>
</head>
<body class="h-full flex antialiased bg-slate-950 selection:bg-cyan-500 selection:text-white" 
    x-data="{ 
        mobileMenuOpen: false,
        isDark: localStorage.getItem('mita_theme') !== 'light',
        toggleTheme() {
            this.isDark = !this.isDark;
            if (this.isDark) {
                localStorage.setItem('mita_theme', 'dark');
                document.documentElement.classList.remove('light');
            } else {
                localStorage.setItem('mita_theme', 'light');
                document.documentElement.classList.add('light');
            }
        }
    }">

    <!-- Mobile Sidebar Backdrop -->
    <div x-show="mobileMenuOpen" x-cloak class="fixed inset-0 z-40 bg-slate-950/80 backdrop-blur-sm lg:hidden"
        @click="mobileMenuOpen = false"
        x-transition:enter="transition-opacity ease-linear duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition-opacity ease-linear duration-300"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"></div>

    <!-- Sidebar Principal -->
    <aside class="fixed inset-y-0 left-0 z-50 w-72 bg-slate-900/95 border-r border-slate-800/80 flex flex-col transition-transform duration-300 ease-in-out lg:translate-x-0"
        :class="mobileMenuOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'">
        
        <!-- Logo y Cabecera de la Marca -->
        <div class="h-16 flex items-center justify-between px-6 border-b border-slate-800">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-cyan-600 to-sky-400 flex items-center justify-center text-white shadow-md shadow-cyan-900/40">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                    </svg>
                </div>
                <div>
                    <span class="text-xl font-bold tracking-tight text-white">MiTa</span>
                    <span class="block text-[10px] uppercase font-semibold tracking-wider text-cyan-400">Gestión Integral</span>
                </div>
            </a>
            <button @click="mobileMenuOpen = false" class="lg:hidden text-slate-400 hover:text-white">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <!-- Enlaces de Navegación por Módulos -->
        <nav class="flex-1 px-4 py-4 space-y-6 overflow-y-auto text-xs font-medium">

            <!-- Panel General -->
            <div>
                <a href="{{ route('dashboard') }}"
                    class="flex items-center gap-3 px-3 py-2 rounded-xl text-slate-200 hover:bg-slate-800/80 hover:text-white transition {{ request()->routeIs('dashboard') ? 'bg-cyan-600/15 text-cyan-400 border border-cyan-500/20 font-semibold' : '' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                    </svg>
                    <span>Panel Principal</span>
                </a>
            </div>

            <!-- Módulo 1: Recursos Humanos -->
            @hasanyrole(['admin', 'rrhh', 'planner'])
            <div>
                <div class="px-3 text-[10px] font-semibold uppercase tracking-wider text-slate-400 mb-1.5 flex items-center justify-between">
                    <span>1. Recursos Humanos</span>
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                </div>
                <div class="space-y-1">
                    <a href="{{ route('rrhh.empleados') }}"
                        class="flex items-center gap-3 px-3 py-2 rounded-xl text-slate-300 hover:bg-slate-800/80 hover:text-white transition {{ request()->routeIs('rrhh.empleados*') ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 font-semibold' : '' }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                        </svg>
                        <span>Empleados y Fichas</span>
                    </a>
                    <a href="{{ route('rrhh.ausencias') }}"
                        class="flex items-center gap-3 px-3 py-2 rounded-xl text-slate-300 hover:bg-slate-800/80 hover:text-white transition {{ request()->routeIs('rrhh.ausencias*') ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 font-semibold' : '' }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        <span>Ausencias y Bajas</span>
                    </a>
                    <a href="{{ route('rrhh.incidencias') }}"
                        class="flex items-center gap-3 px-3 py-2 rounded-xl text-slate-300 hover:bg-slate-800/80 hover:text-white transition {{ request()->routeIs('rrhh.incidencias*') ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 font-semibold' : '' }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                        <span>Incidencias</span>
                    </a>
                </div>
            </div>
            @endhasanyrole

            <!-- Módulo 2: Gestión de Servicios -->
            @hasanyrole(['admin', 'planner'])
            <div>
                <div class="px-3 text-[10px] font-semibold uppercase tracking-wider text-slate-400 mb-1.5 flex items-center justify-between">
                    <span>2. Servicios y Clientes</span>
                    <span class="w-2 h-2 rounded-full bg-cyan-500"></span>
                </div>
                <div class="space-y-1">
                    <a href="{{ route('servicios.clientes') }}"
                        class="flex items-center gap-3 px-3 py-2 rounded-xl text-slate-300 hover:bg-slate-800/80 hover:text-white transition {{ request()->routeIs('servicios.clientes*') ? 'bg-cyan-500/10 text-cyan-400 border border-cyan-500/20 font-semibold' : '' }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                        </svg>
                        <span>Clientes e Instalaciones</span>
                    </a>
                    <a href="{{ route('servicios.proyectos') }}"
                        class="flex items-center gap-3 px-3 py-2 rounded-xl text-slate-300 hover:bg-slate-800/80 hover:text-white transition {{ request()->routeIs('servicios.proyectos*') ? 'bg-cyan-500/10 text-cyan-400 border border-cyan-500/20 font-semibold' : '' }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                        </svg>
                        <span>Proyectos y Coberturas</span>
                    </a>
                    <a href="{{ route('servicios.necesidades') }}"
                        class="flex items-center gap-3 px-3 py-2 rounded-xl text-slate-300 hover:bg-slate-800/80 hover:text-white transition {{ request()->routeIs('servicios.necesidades*') ? 'bg-cyan-500/10 text-cyan-400 border border-cyan-500/20 font-semibold' : '' }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span>Necesidades de Servicio</span>
                    </a>
                    <a href="{{ route('servicios.catalogos') }}"
                        class="flex items-center gap-3 px-3 py-2 rounded-xl text-slate-300 hover:bg-slate-800/80 hover:text-white transition {{ request()->routeIs('servicios.catalogos*') ? 'bg-cyan-500/10 text-cyan-400 border border-cyan-500/20 font-semibold' : '' }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                        </svg>
                        <span>Categorías y Actividades</span>
                    </a>
                </div>
            </div>
            @endhasanyrole

            <!-- Módulo 3: Planificación de Personal -->
            @hasanyrole(['admin', 'planner', 'rrhh'])
            <div>
                <div class="px-3 text-[10px] font-semibold uppercase tracking-wider text-slate-400 mb-1.5 flex items-center justify-between">
                    <span>3. Planificación</span>
                    <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                </div>
                <div class="space-y-1">
                    <a href="{{ route('planificacion.cuadrante') }}"
                        class="flex items-center gap-3 px-3 py-2 rounded-xl text-slate-300 hover:bg-slate-800/80 hover:text-white transition {{ request()->routeIs('planificacion.cuadrante*') ? 'bg-amber-500/10 text-amber-400 border border-amber-500/20 font-semibold' : '' }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path>
                        </svg>
                        <span>Cuadrante Semanal / Mensual</span>
                    </a>
                    <a href="{{ route('planificacion.turnos') }}"
                        class="flex items-center gap-3 px-3 py-2 rounded-xl text-slate-300 hover:bg-slate-800/80 hover:text-white transition {{ request()->routeIs('planificacion.turnos*') ? 'bg-amber-500/10 text-amber-400 border border-amber-500/20 font-semibold' : '' }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span>Gestión y Generación de Turnos</span>
                    </a>
                </div>
            </div>
            @endhasanyrole

            <!-- Módulo 4: Control Económico y Horas -->
            @hasanyrole(['admin', 'accountant', 'planner'])
            <div>
                <div class="px-3 text-[10px] font-semibold uppercase tracking-wider text-slate-400 mb-1.5 flex items-center justify-between">
                    <span>4. Control Económico</span>
                    <span class="w-2 h-2 rounded-full bg-indigo-500"></span>
                </div>
                <div class="space-y-1">
                    <a href="{{ route('horas.validacion') }}"
                        class="flex items-center gap-3 px-3 py-2 rounded-xl text-slate-300 hover:bg-slate-800/80 hover:text-white transition {{ request()->routeIs('horas.validacion*') ? 'bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 font-semibold' : '' }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span>Validación de Horas</span>
                    </a>
                    <a href="{{ route('economia.informes') }}"
                        class="flex items-center gap-3 px-3 py-2 rounded-xl text-slate-300 hover:bg-slate-800/80 hover:text-white transition {{ request()->routeIs('economia.informes*') ? 'bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 font-semibold' : '' }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                        <span>Costes y Rentabilidad</span>
                    </a>
                    <a href="{{ route('economia.tarifas') }}"
                        class="flex items-center gap-3 px-3 py-2 rounded-xl text-slate-300 hover:bg-slate-800/80 hover:text-white transition {{ request()->routeIs('economia.tarifas*') ? 'bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 font-semibold' : '' }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span>Tarifas de Coste</span>
                    </a>
                    <a href="{{ route('economia.movimientos') }}"
                        class="flex items-center gap-3 px-3 py-2 rounded-xl text-slate-300 hover:bg-slate-800/80 hover:text-white transition {{ request()->routeIs('economia.movimientos*') ? 'bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 font-semibold' : '' }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                        <span>Ingresos y Gastos</span>
                    </a>
                </div>
            </div>
            @endhasanyrole

            <!-- Portal del Empleado -->
            @hasanyrole(['employee', 'admin'])
            <div>
                <div class="px-3 text-[10px] font-semibold uppercase tracking-wider text-slate-400 mb-1.5 flex items-center justify-between">
                    <span>Portal del Empleado</span>
                    <span class="w-2 h-2 rounded-full bg-sky-500"></span>
                </div>
                <div class="space-y-1">
                    <a href="{{ route('portal.empleado') }}"
                        class="flex items-center gap-3 px-3 py-2 rounded-xl text-slate-300 hover:bg-slate-800/80 hover:text-white transition {{ request()->routeIs('portal.empleado*') ? 'bg-sky-500/10 text-sky-400 border border-sky-500/20 font-semibold' : '' }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                        <span>Mis Turnos e Imputación</span>
                    </a>
                </div>
            </div>
            @endhasanyrole

            <!-- Administración de Usuarios -->
            @role('admin')
            <div>
                <div class="px-3 text-[10px] font-semibold uppercase tracking-wider text-slate-400 mb-1.5 flex items-center justify-between">
                    <span>Administración</span>
                    <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                </div>
                <div class="space-y-1">
                    <a href="{{ route('admin.usuarios') }}"
                        class="flex items-center gap-3 px-3 py-2 rounded-xl text-slate-300 hover:bg-slate-800/80 hover:text-white transition {{ request()->routeIs('admin.usuarios*') ? 'bg-rose-500/10 text-rose-400 border border-rose-500/20 font-semibold' : '' }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        <span>Usuarios y Roles</span>
                    </a>
                </div>
            </div>
            @endrole

        </nav>

        <!-- Pie de Usuario y Sesión -->
        <div class="p-4 border-t border-slate-800 bg-slate-900/60">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-full bg-slate-700 border border-slate-600 flex items-center justify-center font-bold text-xs text-white">
                    {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 2)) }}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-semibold text-white truncate">{{ auth()->user()->name ?? 'Usuario' }}</p>
                    <div class="flex items-center gap-1 mt-0.5">
                        @foreach(auth()->user()->roles ?? [] as $r)
                            <span class="px-1.5 py-0.2 rounded text-[9px] font-medium bg-cyan-500/10 text-cyan-400 border border-cyan-500/20">
                                {{ $r->display_name }}
                            </span>
                        @endforeach
                    </div>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" title="Cerrar sesión" class="p-1.5 rounded-lg text-slate-400 hover:text-rose-400 hover:bg-slate-800 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                        </svg>
                    </button>
                </form>
            </div>
        </div>
    </aside>

    <!-- Contenido Principal -->
    <div class="flex-1 flex flex-col min-w-0 lg:pl-72">
        <!-- Topbar Superior -->
        <header class="h-16 bg-slate-900/60 backdrop-blur-md border-b border-slate-800/80 sticky top-0 z-30 flex items-center justify-between px-4 sm:px-6 lg:px-8">
            <div class="flex items-center gap-4">
                <button @click="mobileMenuOpen = true" class="lg:hidden p-2 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>
                <div class="flex items-center gap-2 text-xs">
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-slate-800 text-slate-300 border border-slate-700">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                        Europe/Madrid
                    </span>
                    <span class="hidden sm:inline text-slate-400">·</span>
                    <span class="hidden sm:inline text-slate-400 font-medium">
                        {{ now()->isoFormat('D [de] MMMM [de] YYYY') }}
                    </span>
                </div>
            </div>

            <!-- Accesos rápidos superiores -->
            <div class="flex items-center gap-3">
                <!-- Botón selector de Tema Claro / Oscuro -->
                <button type="button" @click="toggleTheme()"
                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold bg-slate-800 text-slate-300 border border-slate-700 hover:text-white hover:bg-slate-700/80 transition shadow-sm"
                    title="Alternar entre modo claro y modo oscuro">
                    <template x-if="isDark">
                        <span class="flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path>
                            </svg>
                            <span class="hidden sm:inline">Tema Claro</span>
                        </span>
                    </template>
                    <template x-if="!isDark">
                        <span class="flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-sky-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path>
                            </svg>
                            <span class="hidden sm:inline">Tema Oscuro</span>
                        </span>
                    </template>
                </button>

                <a href="{{ route('planificacion.cuadrante') }}"
                    class="hidden md:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold bg-cyan-600/15 text-cyan-400 border border-cyan-500/20 hover:bg-cyan-600/25 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    Ver Cuadrante
                </a>
                
                @if(auth()->user()->two_factor_secret)
                    <span title="Doble factor activado" class="px-2 py-1 rounded-lg text-[10px] font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                        2FA Activo
                    </span>
                @endif
            </div>
        </header>

        <!-- Mensajes Flash de Sesión -->
        <div class="px-4 sm:px-6 lg:px-8 pt-4">
            @if(session('success'))
                <div class="mb-4 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-xs flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        <span>{{ session('success') }}</span>
                    </div>
                </div>
            @endif

            @if(session('error'))
                <div class="mb-4 p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-300 text-xs flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-rose-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                        <span>{{ session('error') }}</span>
                    </div>
                </div>
            @endif

            @if(session('warning'))
                <div class="mb-4 p-4 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-300 text-xs flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-amber-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                        <span>{{ session('warning') }}</span>
                    </div>
                </div>
            @endif
        </div>

        <!-- Cuerpo de la Vista -->
        <main class="flex-1 px-4 sm:px-6 lg:px-8 py-6">
            {{ $slot ?? '' }}
            @yield('content')
        </main>
    </div>

    @livewireScripts
</body>
</html>
