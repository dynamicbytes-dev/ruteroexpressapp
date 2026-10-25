<?php

namespace Tests\Feature\Panel;

use App\Models\Coupon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CuponesPanelTest extends TestCase
{
    use RefreshDatabase;

    private User $auditor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->auditor = User::factory()->auditor()->create();
    }

    public function test_el_auditor_ve_el_listado_de_cupones(): void
    {
        $cupon = Coupon::create([
            'code' => 'BIENVENIDA20',
            'discount_percent' => 20,
            'valid_from' => now()->subDay(),
            'valid_until' => now()->addMonth(),
            'is_active' => true,
            'created_by' => $this->auditor->id,
        ]);

        $response = $this->actingAs($this->auditor)
            ->get(route('panel.cupones.index'));

        $response->assertOk()
            ->assertSee('BIENVENIDA20')
            ->assertSee('20%');
    }

    public function test_el_auditor_crea_un_cupon(): void
    {
        $response = $this->actingAs($this->auditor)
            ->post(route('panel.cupones.store'), [
                'code' => 'PROMO50',
                'discount_percent' => 50,
                'min_purchase' => 5.0,
                'max_discount' => 10.0,
                'valid_from' => now()->toDateString(),
                'valid_until' => now()->addWeeks(2)->toDateString(),
                'max_uses_global' => 100,
                'max_uses_per_user' => 1,
                'is_active' => true,
            ]);

        $response->assertRedirect(route('panel.cupones.index'));

        $this->assertDatabaseHas('coupons', [
            'code' => 'PROMO50',
            'discount_percent' => 50,
        ]);
    }

    public function test_el_auditor_alterna_el_estado_del_cupon(): void
    {
        $cupon = Coupon::create([
            'code' => 'TOGGLE10',
            'discount_percent' => 10,
            'valid_from' => now()->subDay(),
            'is_active' => true,
            'created_by' => $this->auditor->id,
        ]);

        $response = $this->actingAs($this->auditor)
            ->from(route('panel.cupones.index'))
            ->post(route('panel.cupones.toggle', $cupon));

        $response->assertRedirect(route('panel.cupones.index'));
        $cupon->refresh();
        $this->assertFalse($cupon->is_active);

        $this->actingAs($this->auditor)
            ->from(route('panel.cupones.index'))
            ->post(route('panel.cupones.toggle', $cupon));

        $cupon->refresh();
        $this->assertTrue($cupon->is_active);
    }

    public function test_el_auditor_ve_el_detalle_del_cupon(): void
    {
        $cupon = Coupon::create([
            'code' => 'DETALLE15',
            'discount_percent' => 15,
            'valid_from' => now()->subDay(),
            'is_active' => true,
            'created_by' => $this->auditor->id,
        ]);

        $response = $this->actingAs($this->auditor)
            ->get(route('panel.cupones.show', $cupon));

        $response->assertOk()
            ->assertSee('DETALLE15');
    }
}
