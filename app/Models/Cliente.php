<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cliente extends Model
{
    use HasFactory;

    protected $table = 'clientes';

    protected $fillable = [
        'razon_social',
        'nif_cif',
        'persona_contacto',
        'email',
        'telefono',
        'direccion',
        'activo',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function instalaciones(): HasMany
    {
        return $this->hasMany(Instalacion::class, 'cliente_id');
    }

    public function proyectos(): HasMany
    {
        return $this->hasMany(Proyecto::class, 'cliente_id');
    }
}
