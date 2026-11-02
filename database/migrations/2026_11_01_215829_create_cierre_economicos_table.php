<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cierre_economicos', function (Blueprint $table) {
            $table->id();

            // Proyecto y período económico.
            $table->foreignId('proyecto_id')
                ->constrained('proyectos')
                ->restrictOnDelete();

            $table->unsignedSmallInteger('anio');
            $table->unsignedTinyInteger('mes');

            // Fotografía económica del período.
            $table->json('datos_consolidados');

            // Responsable del cierre.
            $table->foreignId('cerrado_por')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamp('cerrado_en');

            $table->timestamps();

            // Un único cierre por proyecto y mes.
            $table->unique(
                ['proyecto_id', 'anio', 'mes'],
                'cierre_economico_periodo_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cierre_economicos');
    }
};