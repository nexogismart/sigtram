<?php

declare(strict_types=1);

namespace App\DTOs\Support;

/**
 * DTO inmutable que describe un archivo adjunto validado y almacenado de forma privada.
 */
final class ArchivoAdjuntoDto
{
    /**
     * @param string $uuid Identificador único seguro del archivo
     * @param string $nombreOriginal Nombre con el que el usuario cargó el archivo
     * @param string $rutaRelativa Ruta dentro del storage privado
     * @param string $mimeTipo Tipo MIME real verificado
     * @param string $extension Extensión permitida validada
     * @param int $tamanoBytes Tamaño en bytes
     * @param string $sha256 Hash criptográfico del archivo
     */
    public function __construct(
        public readonly string $uuid,
        public readonly string $nombreOriginal,
        public readonly string $rutaRelativa,
        public readonly string $mimeTipo,
        public readonly string $extension,
        public readonly int $tamanoBytes,
        public readonly string $sha256
    ) {}
}
