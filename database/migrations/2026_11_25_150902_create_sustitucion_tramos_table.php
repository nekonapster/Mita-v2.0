<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */

    public function up(): void
    {
        Schema::create('sustitucion_tramos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('sustitucion_id')
                ->constrained('sustituciones')
                ->cascadeOnDelete();

            $table->foreignId('empleado_id')
                ->constrained('empleados')
                ->restrictOnDelete();

            $table->timestampTz('inicio');
            $table->timestampTz('fin');

            $table->timestamps();

            $table->index(['sustitucion_id', 'inicio']);
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sustitucion_tramos');
    }
};
