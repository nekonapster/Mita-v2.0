<!DOCTYPE html>
<html lang="es" class="h-full bg-slate-900">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'MiTa') }} - Gestión de Servicios y Personal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
    <script>
        if (localStorage.getItem('mita_theme') === 'light') {
            document.documentElement.classList.add('light');
        } else {
            document.documentElement.classList.remove('light');
        }
    </script>
</head>
<body class="h-full flex flex-col justify-center py-12 sm:px-6 lg:px-8 text-slate-100 antialiased selection:bg-cyan-500 selection:text-white"
    x-data="{ 
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
    
    <!-- Selector flotante de tema -->
    <div class="fixed top-4 right-4 z-50">
        <button type="button" @click="toggleTheme()"
            class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold bg-slate-800/90 text-slate-300 border border-slate-700 hover:text-white hover:bg-slate-700 transition shadow-lg backdrop-blur-md">
            <template x-if="isDark">
                <span class="flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path>
                    </svg>
                    <span>Tema Claro</span>
                </span>
            </template>
            <template x-if="!isDark">
                <span class="flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-sky-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path>
                    </svg>
                    <span>Tema Oscuro</span>
                </span>
            </template>
        </button>
    </div>

    <div class="sm:mx-auto sm:w-full sm:max-w-md text-center">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-cyan-600/20 text-cyan-400 border border-cyan-500/30 shadow-lg shadow-cyan-950">
            <svg class="w-9 h-9" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
            </svg>
        </div>
        <h1 class="mt-4 text-3xl font-extrabold tracking-tight text-white">MiTa</h1>
        <p class="mt-1 text-sm text-slate-400">Plataforma de Gestión de Servicios y Planificación de Personal</p>
    </div>

    <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md px-4 sm:px-0">
        <div class="bg-slate-800/80 backdrop-blur-md py-8 px-6 shadow-2xl rounded-2xl border border-slate-700/60 sm:px-10">
            {{ $slot }}
        </div>
        <p class="mt-6 text-center text-xs text-slate-500">
            &copy; {{ date('Y') }} MiTa Gestión. Entorno operativo Europe/Madrid.
        </p>
    </div>
</body>
</html>
