<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('driver_profiles', function (Blueprint $table) {
            $table->foreignId('city_id')
                ->nullable()
                ->after('user_id')
                ->constrained('cities')
                ->nullOnDelete();
        });

        DB::statement(
            'update driver_profiles
             set city_id = users.city_id
             from users
             where users.id = driver_profiles.user_id and driver_profiles.city_id is null',
        );
    }

    public function down(): void
    {
        Schema::table('driver_profiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('city_id');
        });
    }
};
