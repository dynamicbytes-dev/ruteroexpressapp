<?php

namespace Tests\Feature\Api;

use App\Enums\DocumentType;
use App\Models\User;
use App\Models\UserDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * La selfie vigente se entrega a la app como foto de perfil mediante una URL
 * firmada, sin abrir el resto de los documentos.
 */
class PerfilFotoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function selfiePara(User $usuario, string $mime = 'image/jpeg'): UserDocument
    {
        $documento = UserDocument::factory()
            ->for($usuario)
            ->ofType(DocumentType::Selfie)
            ->create([
                'mime_type' => $mime,
                'original_name' => 'selfie.jpg',
                'file_path' => 'documentos/'.$usuario->id.'/selfie.jpg',
            ]);

        Storage::disk('local')->put($documento->file_path, 'contenido-de-prueba');

        return $documento;
    }

    public function test_el_perfil_incluye_la_foto_firmada_de_la_selfie(): void
    {
        $pasajero = User::factory()->passenger()->approved()->create();
        $selfie = $this->selfiePara($pasajero);

        $this->actingAs($pasajero, 'sanctum')
            ->getJson('/api/perfil')
            ->assertOk()
            ->assertJsonPath(
                'data.user.photo_url',
                fn ($url): bool => is_string($url)
                    && str_contains($url, "/api/perfil/foto/{$selfie->id}")
                    && str_contains($url, "user={$pasajero->id}")
                    && str_contains($url, 'signature='),
            );

        $this->get($selfie->avatarUrl())
            ->assertOk()
            ->assertHeader('content-type', 'image/jpeg');
    }

    public function test_sin_selfie_la_foto_es_null(): void
    {
        $pasajero = User::factory()->passenger()->approved()->create();

        UserDocument::factory()
            ->for($pasajero)
            ->ofType(DocumentType::NationalId)
            ->create();

        $this->actingAs($pasajero, 'sanctum')
            ->getJson('/api/perfil')
            ->assertOk()
            ->assertJsonPath('data.user.photo_url', null);
    }

    public function test_una_selfie_en_pdf_no_se_usa_como_foto(): void
    {
        $pasajero = User::factory()->passenger()->approved()->create();
        $selfie = $this->selfiePara($pasajero, 'application/pdf');

        $this->actingAs($pasajero, 'sanctum')
            ->getJson('/api/perfil')
            ->assertOk()
            ->assertJsonPath('data.user.photo_url', null);

        $this->get($selfie->avatarUrl())->assertNotFound();
    }

    public function test_la_url_no_sirve_para_el_documento_de_otra_persona(): void
    {
        $pasajero = User::factory()->passenger()->approved()->create();
        $otro = User::factory()->passenger()->approved()->create();
        $selfie = $this->selfiePara($pasajero);

        $url = URL::temporarySignedRoute('api.perfil.foto', now()->addMinutes(5), [
            'document' => $selfie->id,
            'user' => $otro->id,
        ]);

        $this->get($url)->assertNotFound();
    }

    public function test_sin_firma_no_se_entrega_la_foto(): void
    {
        $pasajero = User::factory()->passenger()->approved()->create();
        $selfie = $this->selfiePara($pasajero);

        $this->get("/api/perfil/foto/{$selfie->id}?user={$pasajero->id}")
            ->assertForbidden();
    }
}
