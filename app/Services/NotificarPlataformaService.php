<?php

namespace App\Services;

use App\Contracts\Repositories\mod_gm\mod_tram_exte\TramiteExternoRepositoryInterface;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class NotificarPlataformaService
{
    private TramiteExternoRepositoryInterface $tramiteRepo;

    public function __construct(TramiteExternoRepositoryInterface $tramiteRepo)
    {
        $this->tramiteRepo = $tramiteRepo;
    }

    /**
     * Procesa la notificación de un expediente con control de concurrencia y transaccionalidad.
     */
    public function notificar(int $cdgoDtoTrmte, string $fecha, ?string $observaciones, ?UploadedFile $file, string $ip): ?string
    {
        $anio = date('Y');
        $mes = date('m');
        $urlImag = null;

        if ($file) {
            $extension = $file->getClientOriginalExtension();
            $filename = "NOTIF_{$cdgoDtoTrmte}_".time().'.'.$extension;
            $subPath = "adjuntos/notificaciones/{$anio}/{$mes}";
            $path = $file->storeAs($subPath, $filename, 'public');

            if (! $path) {
                throw new \Exception('Error al guardar el archivo físico en el servidor.');
            }

            $urlImag = "notificaciones/{$anio}/{$mes}/".$filename;
        }

        $cargoId = (int) session(\App\Support\SessionKeys::CARGO_ID, 0);
        $userId = (int) (Auth::user()->cdgo_lgin ?? 0);

        $this->tramiteRepo->notificarExpedientePlataforma(
            $cdgoDtoTrmte,
            $fecha,
            $observaciones,
            $urlImag,
            $cargoId,
            $userId,
            $ip
        );

        return $urlImag;
    }
}
