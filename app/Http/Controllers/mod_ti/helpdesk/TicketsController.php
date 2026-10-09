<?php

namespace App\Http\Controllers\mod_ti\helpdesk;

use App\Contracts\Services\SecurityCoreInterface;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\mod_ti\helpdesk\TicketsPantallaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Pantalla TV de tickets (migrado desde helpdesk/pantalla.php).
 */
class TicketsController extends Controller
{
    public function __construct(
        private readonly SecurityCoreInterface $securityCore,
        private readonly TicketsPantallaService $service
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

        return view('mod_ti.helpdesk.pantalla', [
            'routes' => [
                'datos' => route('helpdesk.pantalla.datos'),
                'index' => route('helpdesk.pantalla'),
            ],
            'audioAlerta' => asset('mod_ti/helpdesk/tickets/sonido/alerta.mp3'),
        ]);
    }

    public function datos(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return response()->json(['success' => false, 'error' => 'Sesión no válida.'], 401);
        }

        $cargoId = (int) $request->session()->get('sigtram_cargo_id', 0);
        if ($cargoId <= 0 || ! $this->tienePermiso($user, $cargoId)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }

        return response()->json($this->service->obtenerDatos());
    }

    private function assertPermiso(User $user, int $cargoId): void
    {
        if (! $this->tienePermiso($user, $cargoId)) {
            abort(403, 'No tienes permiso para acceder a este módulo.');
        }
    }

    private function tienePermiso(User $user, int $cargoId): bool
    {
        return $this->securityCore->tienePermiso((int) $user->cdgo_lgin, '/mod_ti/helpdesk/pantalla', $cargoId);
    }
}
