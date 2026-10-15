<?php

declare(strict_types=1);

namespace App\Http\Controllers\Publico;

use App\Contracts\Repositories\mod_gm\mod_tram_exte\IngresoExpedienteRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Support\PublicTrackingToken;
use Illuminate\Http\Request;

class SeguimientoPublicoController extends Controller
{
    public function __construct(
        private readonly IngresoExpedienteRepositoryInterface $repository
    ) {}

    /**
     * Muestra la vista pública de consulta y seguimiento de un expediente.
     */
    public function consultar(Request $request, string $token)
    {
        try {
            $expedienteId = PublicTrackingToken::decode($token);
        } catch (\Throwable $e) {
            // Error de configuración de clave o token malformado: responder 404 uniforme
            abort(404, 'Expediente no encontrado.');
        }

        if ($expedienteId === null || $expedienteId <= 0) {
            abort(404, 'Expediente no encontrado.');
        }

        $datos = $this->repository->obtenerSeguimientoPublico($expedienteId);
        if ($datos === null) {
            abort(404, 'Expediente no encontrado.');
        }

        return response()
            ->view('publico.seguimiento', [
                'expediente' => $datos,
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate')
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('Referrer-Policy', 'no-referrer')
            ->header('X-Frame-Options', 'DENY');
    }
}
