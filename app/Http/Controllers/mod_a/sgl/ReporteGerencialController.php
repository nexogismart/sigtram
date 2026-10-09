<?php

namespace App\Http\Controllers\mod_a\sgl;

use App\Contracts\Services\SecurityCoreInterface;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\mod_a\sgl\ReporteGerencialService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Reporte gerencial de pago a proveedores
 * (migrado desde sigtram_antiguo/.../pago_proveedores/reporte/reporte_gerencial.php).
 */
class ReporteGerencialController extends Controller
{
    public function __construct(
        private readonly SecurityCoreInterface $securityCore,
        private readonly ReporteGerencialService $service
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return redirect()->route('login');
        }

        $cargoId = (int) $request->session()->get('sigtram_cargo_id', 0);
        if ($cargoId <= 0) {
            return redirect()->route('seleccionar.cargo');
        }

        $this->assertPermiso($user, $cargoId);

        $feInicio = trim((string) $request->input('fe_inicio', date('Y-m-01')));
        $feFin = trim((string) $request->input('fe_fin', date('Y-m-d')));

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $feInicio)) {
            $feInicio = date('Y-m-01');
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $feFin)) {
            $feFin = date('Y-m-d');
        }

        $resultado = $this->service->generar($feInicio, $feFin);

        return view('mod_a.sgl.pago_proveedores.reporte.reporte_gerencial', [
            'feInicio' => $feInicio,
            'feFin' => $feFin,
            'filas' => ! empty($resultado['success']) ? ($resultado['data']['filas'] ?? []) : [],
            'totales' => ! empty($resultado['success']) ? ($resultado['data']['totales'] ?? null) : null,
            'error' => empty($resultado['success']) ? ($resultado['error'] ?? 'Error al generar el reporte.') : null,
            'actionUrl' => route('sgl.reporte_gerencial'),
        ]);
    }

    private function assertPermiso(User $user, int $cargoId): void
    {
        if (! $this->tienePermiso($user, $cargoId)) {
            abort(403, 'No tienes permiso para acceder a este módulo.');
        }
    }

    private function tienePermiso(User $user, int $cargoId): bool
    {
        return $this->securityCore->tienePermiso(
            (int) $user->cdgo_lgin,
            '/mod_a/sgl/pago_proveedores/reporte/reporte_gerencial',
            $cargoId
        );
    }
}
