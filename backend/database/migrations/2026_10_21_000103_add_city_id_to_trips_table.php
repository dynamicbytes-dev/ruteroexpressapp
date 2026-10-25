<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->foreignId('city_id')
                ->nullable()
                ->after('driver_id')
                ->constrained('cities')
                ->nullOnDelete();

            $table->index(['city_id', 'status']);
        });

        DB::statement(
            'update trips
             set city_id = users.city_id
             from users
             where users.id = trips.passenger_id and trips.city_id is null',
        );
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropIndex(['city_id', 'status']);
            $table->dropConstrainedForeignId('city_id');
        });
    }
};
