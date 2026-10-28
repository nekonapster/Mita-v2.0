<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Empleado extends Model
{
    use HasFactory;

    protected $table = 'empleados';

    protected $fillable = [
        'nombre',
        'apellidos',
        'dni_nie',
        'email',
        'telefono',
        'categoria_id',
        'activo',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    protected function nombreCompleto(): Attribute
    {
        return Attribute::make(
            get: fn () => trim("{$this->nombre} {$this->apellidos}")
        );
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'categoria_id');
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'employee_id');
    }

    public function contratos(): HasMany
    {
        return $this->hasMany(Contrato::class, 'empleado_id')->orderBy('fecha_inicio', 'desc');
    }

    public function ausencias(): HasMany
    {
        return $this->hasMany(Ausencia::class, 'empleado_id')->orderBy('fecha_hora_inicio', 'desc');
    }

    public function asignaciones(): HasMany
    {
        return $this->hasMany(Asignacion::class, 'empleado_id');
    }

    public function incidencias(): HasMany
    {
        return $this->hasMany(Incidencia::class, 'empleado_id')->orderBy('fecha', 'desc');
    }

    public function tarifasCoste(): HasMany
    {
        return $this->hasMany(TarifaCoste::class, 'empleado_id')->orderBy('fecha_inicio', 'desc');
    }

    /**
     * Comprueba si el empleado tiene un contrato válido en un rango o fecha dada.
     */
    public function tieneContratoVigenteEn(Carbon|string $fechaInicio, Carbon|string|null $fechaFin = null): bool
    {
        $inicio = Carbon::parse($fechaInicio)->toDateString();
        $fin = $fechaFin ? Carbon::parse($fechaFin)->toDateString() : $inicio;

        return $this->contratos()
            ->where('fecha_inicio', '<=', $fin)
            ->where(function ($query) use ($inicio) {
                $query->whereNull('fecha_fin')
                    ->orWhere('fecha_fin', '>=', $inicio);
            })
            ->exists();
    }

    /**
     * Obtiene la tarifa de coste por hora aplicable en una fecha determinada.
     * Prioriza tarifa propia del empleado; si no tiene, usa la de su categoría profesional.
     */
    public function tarifaEnFecha(Carbon|string $fecha): ?float
    {
        $fecha = Carbon::parse($fecha)->toDateString();

        $tarifaEmpleado = $this->tarifasCoste()
            ->where('fecha_inicio', '<=', $fecha)
            ->where(function ($query) use ($fecha) {
                $query->whereNull('fecha_fin')
                    ->orWhere('fecha_fin', '>=', $fecha);
            })
            ->first();

        if ($tarifaEmpleado) {
            return (float) $tarifaEmpleado->coste_hora;
        }

        if ($this->categoria_id) {
            $tarifaCategoria = TarifaCoste::where('categoria_id', $this->categoria_id)
                ->whereNull('empleado_id')
                ->where('fecha_inicio', '<=', $fecha)
                ->where(function ($query) use ($fecha) {
                    $query->whereNull('fecha_fin')
                        ->orWhere('fecha_fin', '>=', $fecha);
                })
                ->first();

            if ($tarifaCategoria) {
                return (float) $tarifaCategoria->coste_hora;
            }
        }

        return null;
    }
}
