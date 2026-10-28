<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proyectos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->restrictOnDelete();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            $table->string('estado')->default('borrador'); // borrador, activo, finalizado, cancelado
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });

        Schema::create('instalacion_proyecto', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proyecto_id')->constrained('proyectos')->cascadeOnDelete();
            $table->foreignId('instalacion_id')->constrained('instalaciones')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['proyecto_id', 'instalacion_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instalacion_proyecto');
        Schema::dropIfExists('proyectos');
    }
};
