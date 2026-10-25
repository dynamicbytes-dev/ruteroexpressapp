<?php

namespace Database\Seeders;

use App\Models\ExchangeRate;
use Illuminate\Database\Seeder;

class ExchangeRateSeeder extends Seeder
{
    public function run(): void
    {
        // Tasa de arranque para que el panel y la app tengan una referencia.
        // El auditor la reemplaza por la del día desde /panel/tasa.
        ExchangeRate::query()->firstOrCreate(
            ['effective_date' => today()->toDateString()],
            ['rate' => 40, 'created_by' => null],
        );
    }
}
