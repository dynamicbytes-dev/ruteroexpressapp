<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_pantalla_de_verificacion_se_muestra(): void
    {
        $auditor = User::factory()->auditor()->unverified()->create();

        $this->actingAs($auditor)->get('/panel/verify-email')->assertOk();
    }

    public function test_el_correo_se_verifica_con_el_enlace_firmado(): void
    {
        $auditor = User::factory()->auditor()->unverified()->create();

        Event::fake();

        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $auditor->id,
            'hash' => sha1($auditor->email),
        ]);

        $this->actingAs($auditor)
            ->get($url)
            ->assertRedirect(route('dashboard', absolute: false).'?verified=1');

        Event::assertDispatched(Verified::class);
        $this->assertTrue($auditor->fresh()->hasVerifiedEmail());
    }

    public function test_no_se_verifica_con_un_hash_invalido(): void
    {
        $auditor = User::factory()->auditor()->unverified()->create();

        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $auditor->id,
            'hash' => sha1('otro-correo@gmail.com'),
        ]);

        $this->actingAs($auditor)->get($url);

        $this->assertFalse($auditor->fresh()->hasVerifiedEmail());
    }
}
