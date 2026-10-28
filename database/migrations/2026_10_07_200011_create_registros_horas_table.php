<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registros_horas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asignacion_id')->unique()->constrained('asignaciones')->restrictOnDelete();
            $table->timestampTz('inicio_real');
            $table->timestampTz('fin_real');
            $table->unsignedInteger('descanso_minutos')->default(0);
            $table->decimal('horas_computables', 5, 2);
            $table->text('observaciones')->nullable();
            $table->string('estado')->default('pendiente_revision'); // pendiente_revision, validado
            $table->foreignId('validado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('validado_en')->nullable();
            $table->foreignId('modificado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registros_horas');
    }
};
