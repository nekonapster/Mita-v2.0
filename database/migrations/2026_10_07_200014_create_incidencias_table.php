<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidencias', function (Blueprint $table) {
            $table->id();
            $table->string('titulo');
            $table->text('descripcion');
            $table->date('fecha');
            $table->foreignId('empleado_id')->nullable()->constrained('empleados')->nullOnDelete();
            $table->foreignId('instalacion_id')->nullable()->constrained('instalaciones')->nullOnDelete();
            $table->foreignId('proyecto_id')->nullable()->constrained('proyectos')->nullOnDelete();
            $table->string('estado')->default('abierta'); // abierta, en_seguimiento, resuelta
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['estado', 'fecha']);
            $table->index('empleado_id');
            $table->index('instalacion_id');
            $table->index('proyecto_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidencias');
    }
};
