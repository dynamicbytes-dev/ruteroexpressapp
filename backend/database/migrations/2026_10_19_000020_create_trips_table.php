<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trips', function (Blueprint $table) {
            $table->id();

            $table->foreignId('passenger_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('status', 20)->default('searching')->index();
            $table->string('payment_method', 12);

            // Puntos A y B. Se guardan como decimales para que la app los lea
            // sin depender de PostGIS y para conservar el snapshot del viaje.
            $table->string('origin_address');
            $table->string('destination_address');
            $table->decimal('origin_lat', 10, 7);
            $table->decimal('origin_lng', 10, 7);
            $table->decimal('destination_lat', 10, 7);
            $table->decimal('destination_lng', 10, 7);

            // Ruta y tarifa congeladas al pedir el viaje.
            $table->decimal('distance_km', 8, 2);
            $table->unsignedInteger('duration_minutes');
            $table->decimal('base_fare', 8, 2);
            $table->decimal('price_per_km', 8, 2);
            $table->decimal('price_per_minute', 8, 2);
            $table->decimal('minimum_fare', 8, 2);
            $table->boolean('minimum_applied')->default(false);
            $table->decimal('dynamic_multiplier', 4, 2);
            $table->decimal('night_surcharge_percent', 5, 2);
            $table->decimal('subtotal', 10, 2);
            $table->decimal('total', 10, 2);
            $table->decimal('commission_percent', 5, 2);
            $table->decimal('commission_amount', 10, 2);
            $table->decimal('driver_earning', 10, 2);
            $table->decimal('exchange_rate', 12, 4)->nullable();
            $table->json('fare_breakdown');

            // Retención de billetera mientras el viaje está vivo.
            $table->decimal('wallet_hold_amount', 10, 2)->default(0);

            // Cascada: índice del radio que se está usando.
            $table->unsignedTinyInteger('search_radius_index')->default(0);

            $table->timestamp('requested_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancelled_by', 12)->nullable();
            $table->string('cancellation_reason')->nullable();

            $table->timestamps();

            $table->index(['passenger_id', 'status']);
            $table->index(['driver_id', 'status']);
            $table->index(['status', 'requested_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trips');
    }
};
