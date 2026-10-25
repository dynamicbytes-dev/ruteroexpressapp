<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('city_id')
                ->nullable()
                ->after('address')
                ->constrained('cities')
                ->nullOnDelete();
        });

        // Las cuentas anteriores a las zonas quedan en la ciudad de lanzamiento.
        // El auditor puede reasignarlas desde el panel.
        $predeterminada = DB::table('cities')->where('slug', 'puerto-la-cruz')->value('id');

        if ($predeterminada !== null) {
            DB::table('users')->whereNull('city_id')->update(['city_id' => $predeterminada]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('city_id');
        });
    }
};
