<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('necesidades_servicio', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proyecto_id')->constrained('proyectos')->restrictOnDelete();
            $table->foreignId('instalacion_id')->constrained('instalaciones')->restrictOnDelete();
            $table->foreignId('actividad_id')->constrained('actividades')->restrictOnDelete();
            $table->foreignId('categoria_id')->constrained('categorias')->restrictOnDelete();
            $table->date('fecha_inicio_vigencia');
            $table->date('fecha_fin_vigencia');
            $table->json('dias_semana'); // array of day numbers: 1 = Lunes, 7 = Domingo
            $table->time('hora_inicio');
            $table->time('hora_fin');
            $table->boolean('cruza_medianoche')->default(false);
            $table->unsignedInteger('plazas_necesarias')->default(1);
            $table->text('observaciones')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['proyecto_id', 'instalacion_id', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('necesidades_servicio');
    }
};
