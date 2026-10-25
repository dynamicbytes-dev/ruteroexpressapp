<?php

namespace Database\Seeders;

use App\Models\City;
use App\Support\Configuracion;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        Configuracion::seedDefaults();

        // Cada zona arranca con las tarifas globales y puede ajustarse aparte.
        City::query()->orderBy('id')->each(
            fn (City $ciudad) => Configuracion::seedCityDefaults($ciudad),
        );
    }
}
