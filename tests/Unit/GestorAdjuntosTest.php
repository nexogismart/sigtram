<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Support\GestorAdjuntos;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

class GestorAdjuntosTest extends TestCase
{
    private GestorAdjuntos $gestor;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->gestor = new GestorAdjuntos(Storage::disk('local'));
    }

    public function test_guarda_archivo_valido_en_storage_privado_con_uuid_y_hash(): void
    {
        $file = UploadedFile::fake()->create('solicitud.pdf', 100, 'application/pdf');

        $dto = $this->gestor->guardar($file, 'externos');

        $this->assertSame('solicitud.pdf', $dto->nombreOriginal);
        $this->assertSame('pdf', $dto->extension);
        $this->assertSame('application/pdf', $dto->mimeTipo);
        $this->assertNotEmpty($dto->uuid);
        $this->assertNotEmpty($dto->sha256);
        $this->assertStringStartsWith('documentos/externos/', $dto->rutaRelativa);

        Storage::disk('local')->assertExists($dto->rutaRelativa);
    }

    public function test_rechaza_archivo_con_extension_prohibida(): void
    {
        $file = UploadedFile::fake()->create('script.php', 10, 'application/x-php');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("no está permitida");

        $this->gestor->guardar($file);
    }

    public function test_rechaza_archivo_con_doble_extension_peligrosa(): void
    {
        $file = UploadedFile::fake()->create('payload.php.pdf', 10, 'application/pdf');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("doble extensión");

        $this->gestor->guardar($file);
    }

    public function test_rechaza_mime_incompatible_con_extension(): void
    {
        $file = UploadedFile::fake()->create('documento.pdf', 10, 'text/plain');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("no coincide con un formato válido");

        $this->gestor->guardar($file);
    }

    public function test_bloquea_intento_de_path_traversal(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Path Traversal');

        $this->gestor->resolverRutaSegura('../../etc/passwd');
    }
}
