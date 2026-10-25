<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            // El tipo se congela al pedir el viaje: si mañana cambia la
            // configuración, el histórico sigue diciendo qué vehículo se usó.
            $table->string('vehicle_type', 12)->default('moto')->after('payment_method')->index();
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropIndex(['vehicle_type']);
            $table->dropColumn('vehicle_type');
        });
    }
};
