<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            // Vehículo
            $table->string('vehicle_brand');
            $table->string('vehicle_model');
            $table->unsignedSmallInteger('vehicle_year');
            $table->string('vehicle_plate', 12)->unique();

            // Disponibilidad: el conductor se activa o desactiva desde la app
            $table->boolean('is_online')->default(false);
            $table->timestamp('went_online_at')->nullable();

            // Última posición conocida (PostGIS). Se actualiza en vivo en la fase F4.
            $table->geography('last_location', subtype: 'point', srid: 4326)->nullable();
            $table->decimal('last_speed_kmh', 5, 2)->nullable();
            $table->unsignedSmallInteger('last_bearing')->nullable();
            $table->timestamp('last_location_at')->nullable();

            // Reputación y penalizaciones
            $table->decimal('rating_average', 3, 2)->default(0);
            $table->unsignedInteger('trips_completed')->default(0);
            $table->unsignedInteger('trips_rejected')->default(0);
            $table->unsignedSmallInteger('rejections_in_window')->default(0);
            $table->timestamp('rejection_window_started_at')->nullable();
            $table->timestamp('blocked_until')->nullable();
            $table->string('blocked_reason')->nullable();

            $table->timestamps();

            $table->index(['is_online', 'blocked_until']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_profiles');
    }
};
