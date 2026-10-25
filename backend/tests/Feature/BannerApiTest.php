<?php

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BannerApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_pasajero_puede_consultar_banners_activos(): void
    {
        $pasajero = User::factory()->create(['role' => 'passenger']);

        Banner::create([
            'title' => 'Promo Especial',
            'subtitle' => 'Descuento para pasajeros',
            'tag' => '50% OFF',
            'background_color' => '#310F38',
            'background_color_end' => '#9A0065',
            'text_color' => '#FFFFFF',
            'action_text' => 'Ver más',
            'target_role' => 'passenger',
            'is_active' => true,
            'priority' => 10,
        ]);

        Banner::create([
            'title' => 'Promo Inactiva',
            'target_role' => 'passenger',
            'is_active' => false,
        ]);

        Banner::create([
            'title' => 'Solo Conductores',
            'target_role' => 'driver',
            'is_active' => true,
        ]);

        Sanctum::actingAs($pasajero);

        $response = $this->getJson('/api/banners');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Promo Especial')
            ->assertJsonPath('data.0.tag', '50% OFF');
    }
}
