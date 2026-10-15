<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\Support\GestorAdjuntos;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Mockery;
use InvalidArgumentException;
use RuntimeException;

class TramiteInternoAdjuntosTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_pdf_valido_se_guarda_en_storage_privado(): void
    {
        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('putFileAs')->once()->andReturn('documentos/internos/2026/10/10/uuid.pdf');
        $disk->shouldReceive('get')->once()->andReturn('%PDF-1.4 mock content');

        $archivo = UploadedFile::fake()->create('oficio_circular.pdf', 500, 'application/pdf');

        $gestor = new GestorAdjuntos($disk);
        $dto = $gestor->guardar($archivo, 'internos');

        $this->assertEquals('oficio_circular.pdf', $dto->nombreOriginal);
        $this->assertEquals('pdf', $dto->extension);
        $this->assertEquals('application/pdf', $dto->mimeTipo);
        $this->assertStringStartsWith('documentos/internos/', $dto->rutaRelativa);
        $this->assertNotEmpty($dto->sha256);
    }

    public function test_rechaza_archivo_con_doble_extension(): void
    {
        $disk = Mockery::mock(Filesystem::class);
        $archivo = UploadedFile::fake()->create('informe.php.pdf', 100, 'application/pdf');

        $gestor = new GestorAdjuntos($disk);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Nombre de archivo sospechoso con doble extensión detectada.');

        $gestor->guardar($archivo, 'internos');
    }

    public function test_rechaza_mime_invalido_o_falso(): void
    {
        $disk = Mockery::mock(Filesystem::class);
        // Archivo con extensión .pdf pero MIME de texto/script php
        $archivo = UploadedFile::fake()->create('script.pdf', 100, 'text/x-php');

        $gestor = new GestorAdjuntos($disk);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El contenido del archivo no coincide con un formato válido');

        $gestor->guardar($archivo, 'internos');
    }

    public function test_zip_valido_permitido(): void
    {
        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('putFileAs')->once()->andReturn('documentos/internos/2026/10/10/uuid.zip');
        $disk->shouldReceive('get')->once()->andReturn('PK zip content');

        $archivo = UploadedFile::fake()->create('anexos.zip', 200, 'application/zip');

        $gestor = new GestorAdjuntos($disk);
        $dto = $gestor->guardar($archivo, 'internos');

        $this->assertEquals('zip', $dto->extension);
        $this->assertEquals('application/zip', $dto->mimeTipo);
    }

    public function test_rechaza_archivo_sobredimensionado(): void
    {
        $disk = Mockery::mock(Filesystem::class);
        // Excede 25 MB (26 MB = 26624 KB)
        $archivo = UploadedFile::fake()->create('pesado.pdf', 26624, 'application/pdf');

        $gestor = new GestorAdjuntos($disk);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El archivo excede el tamaño máximo permitido');

        $gestor->guardar($archivo, 'internos');
    }

    public function test_error_de_persistencia_lanza_excepcion(): void
    {
        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('putFileAs')->once()->andReturn(false); // Falla el disco

        $archivo = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');

        $gestor = new GestorAdjuntos($disk);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No se pudo guardar el archivo en el storage privado.');

        $gestor->guardar($archivo, 'internos');
    }

    public function test_eliminar_previene_traversal(): void
    {
        $disk = Mockery::mock(Filesystem::class);
        $gestor = new GestorAdjuntos($disk);

        $this->assertFalse($gestor->eliminar('../etc/passwd'));
        $this->assertFalse($gestor->eliminar('/var/log/syslog'));
    }
}