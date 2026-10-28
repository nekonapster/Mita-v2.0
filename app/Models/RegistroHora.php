<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegistroHora extends Model
{
    use HasFactory;

    public const ESTADO_PENDIENTE = 'pendiente_revision';
    public const ESTADO_VALIDADO = 'validado';

    protected $table = 'registros_horas';

    protected $fillable = [
        'asignacion_id',
        'inicio_real',
        'fin_real',
        'descanso_minutos',
        'horas_computables',
        'observaciones',
        'estado',
        'validado_por',
        'validado_en',
        'modificado_por',
    ];

    protected function casts(): array
    {
        return [
            'inicio_real' => 'datetime',
            'fin_real' => 'datetime',
            'descanso_minutos' => 'integer',
            'horas_computables' => 'decimal:2',
            'validado_en' => 'datetime',
        ];
    }

    public function asignacion(): BelongsTo
    {
        return $this->belongsTo(Asignacion::class, 'asignacion_id');
    }

    public function validador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validado_por');
    }

    public function modificador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'modificado_por');
    }

    /**
     * Calcula las horas computables descontando descansos y evitando duraciones negativas.
     */
    public static function calcularHoras(Carbon|string $inicio, Carbon|string $fin, int $descansoMinutos = 0): float
    {
        $inicio = Carbon::parse($inicio);
        $fin = Carbon::parse($fin);

        $minutosTotales = max(0, $inicio->diffInMinutes($fin, false));
        $minutosEfectivos = max(0, $minutosTotales - $descansoMinutos);

        return round($minutosEfectivos / 60, 2);
    }
}
