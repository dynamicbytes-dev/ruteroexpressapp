<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// La cascada no puede depender de que la app esté abierta.
Schedule::command('viajes:expirar-ofertas')->everyMinute()->withoutOverlapping();

// Avisos de documentos (30/15/5 días) y paso a revisión al vencer.
Schedule::command('documentos:avisar-vencimientos')->dailyAt('08:00')->withoutOverlapping();

// Si la app del conductor dejó de reportar, se marca desconectado solo.
Schedule::command('conductores:desconectar-inactivos')->everyMinute()->withoutOverlapping();

// Tasa de cambio del dólar desde el BCV (o fuente externa configurada).
Schedule::command('tasa:actualizar')->dailyAt('08:00')->withoutOverlapping();
