<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('turnos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('necesidad_servicio_id')->nullable()->constrained('necesidades_servicio')->nullOnDelete();
            $table->foreignId('proyecto_id')->constrained('proyectos')->restrictOnDelete();
            $table->foreignId('instalacion_id')->constrained('instalaciones')->restrictOnDelete();
            $table->foreignId('actividad_id')->constrained('actividades')->restrictOnDelete();
            $table->foreignId('categoria_id')->constrained('categorias')->restrictOnDelete();
            $table->date('fecha');
            $table->timestampTz('hora_inicio');
            $table->timestampTz('hora_fin');
            $table->unsignedInteger('plazas_necesarias')->default(1);
            $table->string('estado')->default('borrador'); // borrador, publicado, cancelado
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index(['fecha', 'estado', 'proyecto_id', 'instalacion_id']);
            $table->index(['necesidad_servicio_id', 'fecha', 'hora_inicio']);
        });

        Schema::create('asignaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('turno_id')->constrained('turnos')->restrictOnDelete();
            $table->foreignId('empleado_id')->constrained('empleados')->restrictOnDelete();
            $table->string('estado')->default('asignado'); // asignado, confirmado, cancelado
            $table->boolean('tiene_conflicto')->default(false);
            $table->text('motivo_conflicto')->nullable();
            $table->timestamps();

            $table->unique(['turno_id', 'empleado_id']);
            $table->index(['empleado_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asignaciones');
        Schema::dropIfExists('turnos');
    }
};
