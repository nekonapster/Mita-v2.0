
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sustituciones', function (Blueprint $table) {
            $table->id();

            $table->foreignId('ausencia_id')
                ->constrained('ausencias')
                ->restrictOnDelete();

            $table->foreignId('turno_id')
                ->nullable()
                ->constrained('turnos')
                ->restrictOnDelete();

            $table->string('modalidad_pago');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sustituciones');
    }
};
