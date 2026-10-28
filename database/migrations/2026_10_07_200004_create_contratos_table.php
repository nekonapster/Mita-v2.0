<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contratos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empleado_id')->constrained('empleados')->restrictOnDelete();
            $table->string('tipo_contrato');
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            $table->decimal('jornada_semanal_horas', 5, 2);
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index(['empleado_id', 'fecha_inicio', 'fecha_fin']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contratos');
    }
};
