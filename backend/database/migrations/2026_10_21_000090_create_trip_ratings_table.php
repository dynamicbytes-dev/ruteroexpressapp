<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Calificación del pasajero al conductor después de un viaje.
 *
 * Un viaje se califica una sola vez (la columna `trip_id` es única). El
 * promedio y la cantidad se mantienen en `driver_profiles` para no tener que
 * agregar en cada consulta de la cascada o del panel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('passenger_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained('users')->cascadeOnDelete();

            $table->unsignedTinyInteger('rating');
            $table->string('comment', 500)->nullable();

            // Solo creación: una calificación no se edita.
            $table->timestamp('created_at')->nullable();

            $table->index(['driver_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_ratings');
    }
};
