<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Conductores favoritos por pasajero.
 *
 * Se llena al calificar un viaje o desde la tarjeta del conductor. La cascada
 * de asignación los intenta primero cuando están en línea y cerca.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('passenger_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['passenger_id', 'driver_id']);
            $table->index('driver_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_favorites');
    }
};
