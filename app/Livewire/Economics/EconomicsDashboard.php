<?php

namespace App\Livewire\Economics;

use App\Models\Instalacion;
use App\Models\Proyecto;
use App\Services\EconomicReportService;
use Livewire\Component;
use App\Models\CierreEconomico;
use App\Models\User;
use App\Services\EconomicClosingService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class EconomicsDashboard extends Component
{
    public ?int $proyectoSeleccionado = null;
    public ?int $instalacionSeleccionada = null;
    public string $fechaDesde = '';
    public string $fechaHasta = '';
    public int $anioCierre;
    public int $mesCierre;

    public bool $modalCierreOpen = false;
    public ?string $tokenCierre = null;

    public function mount(?int $proyectoSeleccionado = null): void
    {
        $this->proyectoSeleccionado = $proyectoSeleccionado ?: Proyecto::first()?->id;
        $this->fechaDesde = now()->startOfYear()->toDateString();
        $this->fechaHasta = now()->endOfYear()->toDateString();
        $this->anioCierre = now()->subMonth()->year;
        $this->mesCierre = now()->subMonth()->month;
    }

    public function prepararCierre(): void
    {
        $usuario = auth()->user();

        // Solo administradores y contables.
        if (
            !$usuario ||
            (
                !$usuario->isAdmin()
                && !$usuario->roles()
                    ->where('name', \App\Models\Role::ACCOUNTANT)
                    ->exists()
            )
        ) {
            abort(403);
        }

        // Validar el período antes de abrir la confirmación.
        $this->validate([
            'proyectoSeleccionado' => 'required|exists:proyectos,id',
            'anioCierre' => 'required|integer|between:2000,9999',
            'mesCierre' => 'required|integer|between:1,12',
        ]);

        // Invalidar una confirmación anterior de este componente.
        if ($this->tokenCierre) {
            Cache::forget('mita.cierre.' . $this->tokenCierre);
        }

        // Generar un identificador único para esta confirmación.
        $this->tokenCierre = (string) Str::uuid();

        // Guardar los datos exclusivamente en el servidor.
        Cache::put(
            'mita.cierre.' . $this->tokenCierre,
            [
                'usuario_id' => $usuario->id,
                'proyecto_id' => $this->proyectoSeleccionado,
                'anio' => $this->anioCierre,
                'mes' => $this->mesCierre,
            ],
            now()->addMinutes(10)
        );

        $this->modalCierreOpen = true;
    }

    public function cancelarCierre(): void
    {
        if ($this->tokenCierre) {
            Cache::forget('mita.cierre.' . $this->tokenCierre);
        }

        $this->tokenCierre = null;
        $this->modalCierreOpen = false;
    }

    public function ejecutarCierre(
        EconomicClosingService $closingService
    ): void {
        $usuario = Auth::user();

        // 1. Verificar permisos.
        if (
            !$usuario instanceof User ||
            (
                !$usuario->isAdmin()
                && !$usuario->roles()
                    ->where('name', \App\Models\Role::ACCOUNTANT)
                    ->exists()
            )
        ) {
            throw new AuthorizationException(
                'No tienes permisos para realizar cierres económicos.'
            );
        }

        // 2. Comprobar que existe un token.
        if (!$this->tokenCierre) {
            throw new AuthorizationException(
                'No existe una confirmación válida para este cierre económico.'
            );
        }

        $token = $this->tokenCierre;
        $clave = 'mita.cierre.' . $token;

        // 3. Obtener un bloqueo exclusivo para este token.
        $bloqueo = Cache::lock(
            'mita.cierre.lock.' . $token,
            30
        );

        // Si otra solicitud tiene el bloqueo, rechazar.
        if (!$bloqueo->get()) {
            throw new AuthorizationException(
                'Esta confirmación ya está siendo procesada.'
            );
        }

        try {
            // 4. Recuperar la confirmación dentro del bloqueo.
            $pendiente = Cache::get($clave);

            // 5. Validar usuario, proyecto y período.
            if (
                !is_array($pendiente)
                || ($pendiente['usuario_id'] ?? null) !== $usuario->id
                || ($pendiente['proyecto_id'] ?? null) !== $this->proyectoSeleccionado
                || ($pendiente['anio'] ?? null) !== $this->anioCierre
                || ($pendiente['mes'] ?? null) !== $this->mesCierre
            ) {
                throw new AuthorizationException(
                    'No existe una confirmación válida para este cierre económico.'
                );
            }

            // 6. Consumir el token mientras mantenemos el bloqueo.
            Cache::forget($clave);
            $this->tokenCierre = null;

            // 7. Ejecutar el cierre económico.
            $proyecto = Proyecto::findOrFail($pendiente['proyecto_id']);

            $closingService->cerrarPeriodo(
                $proyecto,
                $pendiente['anio'],
                $pendiente['mes'],
                $usuario
            );

            $this->modalCierreOpen = false;

            session()->flash(
                'success',
                'El período económico se ha cerrado correctamente.'
            );
        } finally {
            // 8. Liberar el bloqueo incluso si ocurre un error.
            $bloqueo->release();
        }
    }

    public function render()
    {
        $proyecto = $this->proyectoSeleccionado ? Proyecto::with('instalaciones')->find($this->proyectoSeleccionado) : null;
        $informe = null;

        if ($proyecto) {
            $econService = app(EconomicReportService::class);

            $informe = $econService->obtenerInformeEconomico(
                $proyecto,
                $this->fechaDesde ?: now()->startOfYear()->toDateString(),
                $this->fechaHasta ?: now()->endOfYear()->toDateString(),
                $this->instalacionSeleccionada
            );
        }

        $instalacionesDelProyecto = $proyecto?->instalaciones ?? collect();

        // Historial de cierres del proyecto seleccionado.
        $historialCierres = $proyecto
            ? CierreEconomico::query()
            ->with('usuarioCierre')
            ->where('proyecto_id', $proyecto->id)
            ->orderByDesc('anio')
            ->orderByDesc('mes')
            ->get()
            : collect();

        return view('livewire.economics.economics-dashboard', [
            'proyectos' => Proyecto::orderBy('nombre')->get(),
            'instalacionesDelProyecto' => $instalacionesDelProyecto,
            'proyecto' => $proyecto,
            'informe' => $informe,
            'historialCierres' => $historialCierres,
        ])->layout('layouts.app', ['title' => 'Control Económico e Informes']);
    }
}
