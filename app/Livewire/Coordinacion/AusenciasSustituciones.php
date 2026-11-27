<?php

namespace App\Livewire\Coordinacion;

use Livewire\Component;
use App\Models\Empleado;
use App\Models\Ausencia;
use App\Models\Sustitucion;
use App\Models\SustitucionTramo;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\WithPagination;

class AusenciasSustituciones extends Component
{
    use WithPagination;

    public ?int $empleadoAusenteId = null;
    public string $tipoAusencia = 'otro';
    public string $fechaHoraInicio = '';
    public string $fechaHoraFin = '';
    public string $observaciones = '';
    public string $modalidadPago = 'empresa';
    public bool $guardadoCorrectamente = false;
    public ?int $empleadoHistorialFiltro = null;

    public array $sustitutos = [];

    public int $siguienteTramoId = 0;

    public function agregarSustituto(): void
    {
        $this->sustitutos[] = [
            'id' => ++$this->siguienteTramoId,
            'empleado_id' => '',
            'inicio' => '',
            'fin' => '',
        ];
    }

    public function eliminarSustituto(int $id): void
    {
        $this->sustitutos = array_values(
            array_filter(
                $this->sustitutos,
                fn(array $tramo) => $tramo['id'] !== $id
            )
        );
    }

    public function calcularCobertura(): array
    {
        if (!$this->fechaHoraInicio || !$this->fechaHoraFin) {
            return ['cubiertas' => 0, 'sin_cubrir' => 0];
        }

        try {
            $inicio = \Carbon\Carbon::parse($this->fechaHoraInicio);
            $fin = \Carbon\Carbon::parse($this->fechaHoraFin);

            if ($fin->lessThanOrEqualTo($inicio)) {
                return ['cubiertas' => 0, 'sin_cubrir' => 0];
            }

            $intervalos = [];

            foreach ($this->sustitutos as $tramo) {
                if (empty($tramo['inicio']) || empty($tramo['fin'])) {
                    continue;
                }

                $desde = \Carbon\Carbon::parse($tramo['inicio']);
                $hasta = \Carbon\Carbon::parse($tramo['fin']);

                $desde = $desde->greaterThan($inicio) ? $desde : $inicio;
                $hasta = $hasta->lessThan($fin) ? $hasta : $fin;

                if ($hasta->greaterThan($desde)) {
                    $intervalos[] = [$desde->timestamp, $hasta->timestamp];
                }
            }

            usort($intervalos, fn($a, $b) => $a[0] <=> $b[0]);

            $segundosCubiertos = 0;
            $ultimoFin = null;

            foreach ($intervalos as [$desde, $hasta]) {
                if ($ultimoFin === null || $desde > $ultimoFin) {
                    $segundosCubiertos += $hasta - $desde;
                    $ultimoFin = $hasta;
                } elseif ($hasta > $ultimoFin) {
                    $segundosCubiertos += $hasta - $ultimoFin;
                    $ultimoFin = $hasta;
                }
            }

            $segundosTotales = $fin->timestamp - $inicio->timestamp;

            return [
                'cubiertas' => round($segundosCubiertos / 3600, 2),
                'sin_cubrir' => round(($segundosTotales - $segundosCubiertos) / 3600, 2),
            ];
        } catch (\Exception $e) {
            return ['cubiertas' => 0, 'sin_cubrir' => 0];
        }
    }


    public function guardar(): void
    {
        Gate::authorize('registrarSustituciones');

        if ($this->guardadoCorrectamente) {
            return;
        }

        $datos = $this->validate([
            'empleadoAusenteId' => ['required', 'integer', 'exists:empleados,id'],
            'tipoAusencia' => ['required', 'in:vacaciones,baja,permiso,otro'],
            'fechaHoraInicio' => ['required', 'date'],
            'fechaHoraFin' => ['required', 'date', 'after:fechaHoraInicio'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
            'modalidadPago' => [
                'required',
                'in:empresa,empresa_descuento_horas,monitor_sustituido',
            ],
            'sustitutos' => ['array'],
            'sustitutos.*.empleado_id' => [
                'required',
                'integer',
                'exists:empleados,id',
            ],
            'sustitutos.*.inicio' => ['required', 'date'],
            'sustitutos.*.fin' => ['required', 'date'],
        ]);


        foreach ($datos['sustitutos'] as $index => $tramo) {
            if ((int) $tramo['empleado_id'] === (int) $datos['empleadoAusenteId']) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "sustitutos.{$index}.empleado_id" =>
                    'El empleado ausente no puede sustituirse a sí mismo.',
                ]);
            }
        }

        DB::transaction(function () use ($datos): void {

            $zonaHoraria = 'Europe/Madrid';

            $ausencia = Ausencia::create([
                'empleado_id' => $datos['empleadoAusenteId'],
                'tipo' => $datos['tipoAusencia'],
                'fecha_hora_inicio' => Carbon::parse(
                    $datos['fechaHoraInicio'],
                    $zonaHoraria
                )->utc(),
                'fecha_hora_fin' => Carbon::parse(
                    $datos['fechaHoraFin'],
                    $zonaHoraria
                )->utc(),
                'estado' => Ausencia::ESTADO_PENDIENTE,
                'observaciones' => $datos['observaciones'] ?? null,
            ]);

            $sustitucion = Sustitucion::create([
                'ausencia_id' => $ausencia->id,
                'modalidad_pago' => $datos['modalidadPago'],
            ]);

            foreach ($datos['sustitutos'] as $tramo) {
                SustitucionTramo::create([
                    'sustitucion_id' => $sustitucion->id,
                    'empleado_id' => $tramo['empleado_id'],
                    'inicio' => Carbon::parse(
                        $tramo['inicio'],
                        $zonaHoraria
                    )->utc(),
                    'fin' => Carbon::parse(
                        $tramo['fin'],
                        $zonaHoraria
                    )->utc(),
                ]);
            }
        });
        $this->guardadoCorrectamente = true;

        session()->flash(
            'sustitucion_guardada',
            'La ausencia y sus sustituciones se registraron correctamente.'
        );
    }
    
    public function updatedEmpleadoHistorialFiltro(): void
    {
        $this->resetPage();
    }


    public function render()
    {
        return view('livewire.coordinacion.ausencias-sustituciones', [
            'empleados' => Empleado::where('activo', true)
                ->orderBy('nombre')
                ->orderBy('apellidos')
                ->get(),

            'historial' => Sustitucion::with([
                'ausencia.empleado',
                'tramos.empleado',
            ])
                ->when(
                    $this->empleadoHistorialFiltro !== null,
                    fn($query) => $query->whereHas(
                        'ausencia',
                        fn($ausencias) => $ausencias->where(
                            'empleado_id',
                            $this->empleadoHistorialFiltro
                        )
                    )
                )
                ->latest('id')
                ->paginate(10),

        ])->layout('layouts.app', [
            'title' => 'Ausencias y Sustituciones',
        ]);
    }
}
