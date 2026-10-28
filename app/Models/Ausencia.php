<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ausencia extends Model
{
    use HasFactory;

    public const ESTADO_PENDIENTE = 'pendiente';
    public const ESTADO_APROBADA = 'aprobada';
    public const ESTADO_RECHAZADA = 'rechazada';

    public const TIPO_VACACIONES = 'vacaciones';
    public const TIPO_BAJA = 'baja';
    public const TIPO_PERMISO = 'permiso';
    public const TIPO_OTRO = 'otro';

    protected $table = 'ausencias';

    protected $fillable = [
        'empleado_id',
        'tipo',
        'fecha_hora_inicio',
        'fecha_hora_fin',
        'estado',
        'observaciones',
        'aprobado_por',
        'aprobado_en',
    ];

    protected function casts(): array
    {
        return [
            'fecha_hora_inicio' => 'datetime',
            'fecha_hora_fin' => 'datetime',
            'aprobado_en' => 'datetime',
        ];
    }

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Empleado::class, 'empleado_id');
    }

    public function aprobador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobado_por');
    }

    public function isAprobada(): bool
    {
        return $this->estado === self::ESTADO_APROBADA;
    }

    public function isPendiente(): bool
    {
        return $this->estado === self::ESTADO_PENDIENTE;
    }

    public function isRechazada(): bool
    {
        return $this->estado === self::ESTADO_RECHAZADA;
    }
}
