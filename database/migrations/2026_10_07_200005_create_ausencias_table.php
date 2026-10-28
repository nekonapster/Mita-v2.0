<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ausencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empleado_id')->constrained('empleados')->restrictOnDelete();
            $table->string('tipo'); // vacaciones, baja, permiso, otro
            $table->timestampTz('fecha_hora_inicio');
            $table->timestampTz('fecha_hora_fin');
            $table->string('estado')->default('pendiente'); // pendiente, aprobada, rechazada
            $table->text('observaciones')->nullable();
            $table->foreignId('aprobado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('aprobado_en')->nullable();
            $table->timestamps();

            $table->index(['empleado_id', 'estado', 'fecha_hora_inicio', 'fecha_hora_fin']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ausencias');
    }
};
