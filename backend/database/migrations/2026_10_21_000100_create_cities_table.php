<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Ciudades donde opera GAZ (zona oriental del país). */
    private const CIUDADES = [
        ['Puerto La Cruz', 'puerto-la-cruz', 'Anzoátegui', 10.2139000, -64.6325000],
        ['Barcelona', 'barcelona', 'Anzoátegui', 10.1394000, -64.7013000],
        ['Lechería', 'lecheria', 'Anzoátegui', 10.1906000, -64.6918000],
        ['Maturín', 'maturin', 'Monagas', 9.7457000, -63.1767000],
        ['Margarita', 'margarita', 'Nueva Esparta', 10.9577000, -63.8697000],
        ['Carúpano', 'carupano', 'Sucre', 10.6652000, -63.2530000],
        ['Cumaná', 'cumana', 'Sucre', 10.4639000, -64.1722000],
    ];

    public function up(): void
    {
        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('slug', 80)->unique();
            $table->string('state', 80);
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        $ahora = now();

        DB::table('cities')->insert(array_map(
            fn (array $ciudad): array => [
                'name' => $ciudad[0],
                'slug' => $ciudad[1],
                'state' => $ciudad[2],
                'lat' => $ciudad[3],
                'lng' => $ciudad[4],
                'is_active' => true,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ],
            self::CIUDADES,
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('cities');
    }
};
