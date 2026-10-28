<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! (bool) ($user->fresh()?->activo ?? false)) {

            // Cerrar la sesión del usuario
            Auth::logout();

            // Invalidar la sesión
            $request->session()->invalidate();

            // Regenerar el token CSRF
            $request->session()->regenerateToken();

            // Redirigir al login
            return redirect()->route('login');
        }

        return $next($request);
    }
}