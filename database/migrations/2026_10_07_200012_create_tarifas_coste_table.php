<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tarifas_coste', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empleado_id')->nullable()->constrained('empleados')->restrictOnDelete();
            $table->foreignId('categoria_id')->nullable()->constrained('categorias')->restrictOnDelete();
            $table->decimal('coste_hora', 10, 2);
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index(['empleado_id', 'fecha_inicio', 'fecha_fin']);
            $table->index(['categoria_id', 'fecha_inicio', 'fecha_fin']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tarifas_coste');
    }
};
