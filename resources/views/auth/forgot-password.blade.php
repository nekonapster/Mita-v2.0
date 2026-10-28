<x-guest-layout>
    <div class="mb-6">
        <h2 class="text-xl font-bold text-white">Recuperar Contraseña</h2>
        <p class="text-xs text-slate-400 mt-1">Indica tu correo electrónico registrado y te enviaremos un enlace para restablecer tu contraseña.</p>
    </div>

    @if (session('status'))
        <div class="mb-4 p-3 rounded-lg bg-emerald-500/10 border border-emerald-500/20 text-xs text-emerald-400">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-4 p-3 rounded-lg bg-rose-500/10 border border-rose-500/20 text-xs text-rose-400">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf

        <div>
            <label for="email" class="block text-xs font-medium text-slate-300">Correo Electrónico</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                class="mt-1 block w-full rounded-xl border border-slate-700 bg-slate-900/80 px-3.5 py-2.5 text-sm text-white placeholder-slate-500 focus:border-cyan-500 focus:outline-none focus:ring-1 focus:ring-cyan-500">
        </div>

        <div class="flex items-center justify-between">
            <a href="{{ route('login') }}" class="text-xs font-medium text-slate-400 hover:text-white">
                ← Volver al inicio de sesión
            </a>
            <button type="submit"
                class="py-2.5 px-4 border border-transparent rounded-xl shadow-md text-sm font-semibold text-white bg-cyan-600 hover:bg-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-500 transition">
                Enviar Enlace
            </button>
        </div>
    </form>
</x-guest-layout>
