<?php

namespace Tests\Feature\Trips;

use App\Domain\Trips\FareCalculator;
use App\Support\Configuracion;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FareCalculatorTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function calcular(string $momento, float $km = 5, int $minutos = 12): array
    {
        Carbon::setTestNow(Carbon::parse($momento));

        return app(FareCalculator::class)->calcular($km, $minutos);
    }

    public function test_calcula_la_tarifa_diurna_con_el_desglose_completo(): void
    {
        $tarifa = $this->calcular('2026-10-19 12:00:00');

        $this->assertSame(3.35, $tarifa['subtotal']);
        $this->assertSame(3.35, $tarifa['total']);
        $this->assertSame(0.34, $tarifa['commission_amount']);
        $this->assertSame(3.01, $tarifa['driver_earning']);
        $this->assertFalse($tarifa['night']);
        $this->assertFalse($tarifa['minimum_applied']);
    }

    public function test_aplica_el_recargo_nocturno_en_la_franja_configurada(): void
    {
        $tarifa = $this->calcular('2026-10-19 20:00:00');

        $this->assertTrue($tarifa['night']);
        $this->assertSame(20.0, $tarifa['night_surcharge_percent']);
        $this->assertSame(4.02, $tarifa['total']);
        $this->assertSame(0.40, $tarifa['commission_amount']);
        $this->assertSame(3.62, $tarifa['driver_earning']);
    }

    public function test_respeta_la_tarifa_minima(): void
    {
        $tarifa = $this->calcular('2026-10-19 12:00:00', 0.5, 2);

        $this->assertTrue($tarifa['minimum_applied']);
        $this->assertSame(1.50, $tarifa['subtotal']);
        $this->assertSame(1.50, $tarifa['total']);
    }

    public function test_aplica_el_multiplicador_dinamico_configurado(): void
    {
        Configuracion::put('dinamica_actual', 1.5);

        $tarifa = $this->calcular('2026-10-19 12:00:00');

        $this->assertSame(1.5, $tarifa['dynamic_multiplier']);
        $this->assertSame(5.03, $tarifa['total']);
        $this->assertSame(0.50, $tarifa['commission_amount']);
        $this->assertSame(4.53, $tarifa['driver_earning']);
    }

    public function test_el_multiplicador_actual_nunca_sale_de_su_rango(): void
    {
        Configuracion::put('dinamica_actual', 99);

        $tarifa = $this->calcular('2026-10-19 12:00:00');

        $this->assertSame(1.8, $tarifa['dynamic_multiplier']);
    }
}
