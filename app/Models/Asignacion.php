<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Asignacion extends Model
{
    use HasFactory;

    public const ESTADO_ASIGNADO = 'asignado';
    public const ESTADO_CONFIRMADO = 'confirmado';
    public const ESTADO_CANCELADO = 'cancelado';

    protected $table = 'asignaciones';

    protected $attributes = [
        'tiene_conflicto' => false,
        'estado' => self::ESTADO_ASIGNADO,
    ];

    protected $fillable = [
        'turno_id',
        'empleado_id',
        'estado',
        'tiene_conflicto',
        'motivo_conflicto',
    ];

    protected function casts(): array
    {
        return [
            'tiene_conflicto' => 'boolean',
        ];
    }

    public function turno(): BelongsTo
    {
        return $this->belongsTo(Turno::class, 'turno_id');
    }

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Empleado::class, 'empleado_id');
    }

    public function registroHora(): HasOne
    {
        return $this->hasOne(RegistroHora::class, 'asignacion_id');
    }
}
