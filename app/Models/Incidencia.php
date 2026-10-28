<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Incidencia extends Model
{
    use HasFactory;

    public const ESTADO_ABIERTA = 'abierta';
    public const ESTADO_SEGUIMIENTO = 'en_seguimiento';
    public const ESTADO_RESUELTA = 'resuelta';

    protected $table = 'incidencias';

    protected $fillable = [
        'titulo',
        'descripcion',
        'fecha',
        'empleado_id',
        'instalacion_id',
        'proyecto_id',
        'estado',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
        ];
    }

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Empleado::class, 'empleado_id');
    }

    public function instalacion(): BelongsTo
    {
        return $this->belongsTo(Instalacion::class, 'instalacion_id');
    }

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class, 'proyecto_id');
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
