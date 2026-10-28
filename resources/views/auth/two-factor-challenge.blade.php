<x-guest-layout>
    <div class="mb-6">
        <h2 class="text-xl font-bold text-white">Doble Factor de Autenticación</h2>
        <p class="text-xs text-slate-400 mt-1">Introduce el código generado por tu aplicación autenticadora (Google Authenticator / Authy).</p>
    </div>

    @if ($errors->any())
        <div class="mb-4 p-3 rounded-lg bg-rose-500/10 border border-rose-500/20 text-xs text-rose-400">
            <ul class="list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div x-data="{ recovery: false }">
        <form method="POST" action="{{ route('two-factor.login') }}" class="space-y-4">
            @csrf

            <div x-show="!recovery">
                <label for="code" class="block text-xs font-medium text-slate-300">Código de Seguridad (6 dígitos)</label>
                <input id="code" type="text" inputmode="numeric" name="code" autofocus autocomplete="one-time-code"
                    class="mt-1 block w-full text-center tracking-widest text-lg font-mono rounded-xl border border-slate-700 bg-slate-900/80 px-3.5 py-2.5 text-white placeholder-slate-500 focus:border-cyan-500 focus:outline-none focus:ring-1 focus:ring-cyan-500">
            </div>

            <div x-show="recovery" x-cloak>
                <label for="recovery_code" class="block text-xs font-medium text-slate-300">Código de Recuperación</label>
                <input id="recovery_code" type="text" name="recovery_code" autocomplete="one-time-code"
                    class="mt-1 block w-full rounded-xl border border-slate-700 bg-slate-900/80 px-3.5 py-2.5 text-sm text-white placeholder-slate-500 focus:border-cyan-500 focus:outline-none focus:ring-1 focus:ring-cyan-500">
            </div>

            <div class="flex items-center justify-between text-xs">
                <button type="button" @click="recovery = !recovery" class="text-cyan-400 hover:text-cyan-300">
                    <span x-show="!recovery">Usar código de recuperación</span>
                    <span x-show="recovery" x-cloak>Usar código de autenticación TOTP</span>
                </button>
            </div>

            <button type="submit"
                class="w-full flex justify-center py-2.5 px-4 border border-transparent rounded-xl shadow-md text-sm font-semibold text-white bg-cyan-600 hover:bg-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-500 transition">
                Verificar y Continuar
            </button>
        </form>
    </div>
</x-guest-layout>
