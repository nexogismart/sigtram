<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\PUsrrio;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProfilePhotoTest extends TestCase
{
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aS1kAAAAASUVORK5CYII=';

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.stores.file' => ['driver' => 'array'], 'cache.default' => 'array',
            'pide.credentials_pool' => [['usuario' => 'test', 'clave' => 'test']], 'pide.ruc_institucional' => '12345678901']);
        Cache::forgetDriver('file');
        Http::preventStrayRequests();
    }

    private function signedIn(string $dni = '12345678'): void
    {
        $user = new User(['cdgo_lgin' => 987, 'de_lgin' => 'test']);
        $user->setRelation('usrio', new PUsrrio(['nu_docu' => $dni]));
        $this->actingAs($user);
    }

    private function photoResponse(string $photo): void
    {
        Http::fake(['ws2.pide.gob.pe/*' => Http::response(['consultarResponse' => ['return' => [
            'coResultado' => '0000', 'datosPersona' => ['foto' => $photo],
        ]]])]);
    }

    public function test_only_authenticated_users_can_request_their_photo(): void
    {
        $this->get(route('perfil.foto'))->assertRedirect(route('login'));
        Http::assertNothingSent();
    }

    public function test_returns_valid_portrait_and_ignores_a_document_supplied_by_the_client(): void
    {
        $this->signedIn();
        $this->photoResponse(self::PNG);
        $response = $this->get(route('perfil.foto').'?documento=87654321');
        $response->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertSame(base64_decode(self::PNG), $response->getContent());
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        Http::assertSent(fn ($request) => $request['nuDniConsulta'] === '12345678');
    }

    public function test_caches_the_portrait_without_repeating_the_external_lookup(): void
    {
        $this->signedIn();
        $this->photoResponse(self::PNG);
        $this->get(route('perfil.foto'))->assertOk();
        $this->get(route('perfil.foto'))->assertOk();
        Http::assertSentCount(1);
    }

    public function test_invalid_image_retains_initial_and_is_negatively_cached(): void
    {
        $this->signedIn();
        $this->photoResponse(base64_encode('<script>invalid image</script>'));
        $this->get(route('perfil.foto'))->assertNoContent();
        $this->get(route('perfil.foto'))->assertNoContent();
        Http::assertSentCount(1);
    }

    public function test_missing_dni_does_not_contact_reniec(): void
    {
        $this->signedIn('');
        $this->get(route('perfil.foto'))->assertNoContent();
        Http::assertNothingSent();
    }

    public function test_service_failure_does_not_break_the_profile(): void
    {
        $this->signedIn();
        Http::fake(['ws2.pide.gob.pe/*' => Http::response('', 503)]);
        $this->get(route('perfil.foto'))->assertNoContent();
    }
}
