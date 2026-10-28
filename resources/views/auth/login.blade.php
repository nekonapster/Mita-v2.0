<x-guest-layout>
    <div class="mb-6">
        <h2 class="text-xl font-bold text-white">Iniciar Sesión</h2>
        <p class="text-xs text-slate-400 mt-1">Introduce tus credenciales para acceder a MiTa.</p>
    </div>

    @if (session('status'))
        <div class="mb-4 p-3 rounded-lg bg-emerald-500/10 border border-emerald-500/20 text-xs text-emerald-400">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-4 p-3 rounded-lg bg-rose-500/10 border border-rose-500/20 text-xs text-rose-400">
            <ul class="list-disc list-inside space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="space-y-4" id="loginForm">
        @csrf

        <div>
            <label for="email" class="block text-xs font-medium text-slate-300">Correo Electrónico</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                class="mt-1 block w-full rounded-xl border border-slate-700 bg-slate-900/80 px-3.5 py-2.5 text-sm text-white placeholder-slate-500 shadow-sm focus:border-cyan-500 focus:outline-none focus:ring-1 focus:ring-cyan-500">
        </div>

        <div>
            <div class="flex items-center justify-between">
                <label for="password" class="block text-xs font-medium text-slate-300">Contraseña</label>
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" class="text-xs font-medium text-cyan-400 hover:text-cyan-300">
                        ¿Olvidaste tu contraseña?
                    </a>
                @endif
            </div>
            <input id="password" type="password" name="password" required autocomplete="current-password"
                class="mt-1 block w-full rounded-xl border border-slate-700 bg-slate-900/80 px-3.5 py-2.5 text-sm text-white placeholder-slate-500 shadow-sm focus:border-cyan-500 focus:outline-none focus:ring-1 focus:ring-cyan-500">
        </div>

        <div class="flex items-center">
            <input id="remember" type="checkbox" name="remember"
                class="h-4 w-4 rounded border-slate-700 bg-slate-900 text-cyan-600 focus:ring-cyan-500">
            <label for="remember" class="ml-2 block text-xs text-slate-300">Recordar sesión</label>
        </div>

        <div>
            <button type="submit"
                class="w-full flex justify-center py-2.5 px-4 border border-transparent rounded-xl shadow-md text-sm font-semibold text-white bg-cyan-600 hover:bg-cyan-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-slate-800 focus:ring-cyan-500 transition-all duration-150">
                Acceder a la Plataforma
            </button>
        </div>
    </form>

    <!-- Accesos rápidos de demostración -->
    <div class="mt-8 pt-6 border-t border-slate-700/60">
        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-3 text-center">
            Cuentas de demostración (Click para autocompletar)
        </p>
        <div class="grid grid-cols-2 gap-2 text-xs">
            <button type="button" onclick="fillLogin('admin@mita.es', 'password123')"
                class="text-left px-2.5 py-1.5 rounded-lg bg-slate-700/40 hover:bg-slate-700/80 border border-slate-600/50 transition">
                <span class="font-semibold text-cyan-300">Administrador</span>
                <span class="block text-[10px] text-slate-400">admin@mita.es</span>
            </button>
            <button type="button" onclick="fillLogin('rrhh@mita.es', 'password123')"
                class="text-left px-2.5 py-1.5 rounded-lg bg-slate-700/40 hover:bg-slate-700/80 border border-slate-600/50 transition">
                <span class="font-semibold text-emerald-300">Recursos Humanos</span>
                <span class="block text-[10px] text-slate-400">rrhh@mita.es</span>
            </button>
            <button type="button" onclick="fillLogin('planificador@mita.es', 'password123')"
                class="text-left px-2.5 py-1.5 rounded-lg bg-slate-700/40 hover:bg-slate-700/80 border border-slate-600/50 transition">
                <span class="font-semibold text-amber-300">Planificador</span>
                <span class="block text-[10px] text-slate-400">planificador@mita.es</span>
            </button>
            <button type="button" onclick="fillLogin('contable@mita.es', 'password123')"
                class="text-left px-2.5 py-1.5 rounded-lg bg-slate-700/40 hover:bg-slate-700/80 border border-slate-600/50 transition">
                <span class="font-semibold text-indigo-300">Contable</span>
                <span class="block text-[10px] text-slate-400">contable@mita.es</span>
            </button>
            <button type="button" onclick="fillLogin('carlos@mita.es', 'password123')"
                class="text-left px-2.5 py-1.5 rounded-lg bg-slate-700/40 hover:bg-slate-700/80 border border-slate-600/50 transition col-span-2">
                <span class="font-semibold text-sky-300">Empleado (Socorrista)</span>
                <span class="block text-[10px] text-slate-400">carlos@mita.es (portal del empleado)</span>
            </button>
        </div>
    </div>

    <script>
        function fillLogin(email, pass) {
            document.getElementById('email').value = email;
            document.getElementById('password').value = pass;
        }
    </script>
</x-guest-layout>
