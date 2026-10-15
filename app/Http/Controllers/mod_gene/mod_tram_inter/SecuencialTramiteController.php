<?php

declare(strict_types=1);

namespace App\Http\Controllers\mod_gene\mod_tram_inter;

use App\Http\Controllers\Controller;
use App\Services\mod_gene\mod_tram_inter\SecuencialTramiteService;
use App\Support\SessionKeys;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Equivalente a ver_secuencial_area.php
 * Vista BD: /mod_gene/mod_tram_inter/ver_secuencial_area
 */
class SecuencialTramiteController extends Controller
{
    public function __construct(
        private readonly SecuencialTramiteService $secuencialService
    ) {
    }

    public function index(Request $request): View|RedirectResponse
    {
        // Generar número (POST del formulario, como el antiguo)
        if ($request->isMethod('post') && $request->filled('sel_cdgo_tpo_trmte') && (int) $request->input('sel_cdgo_tpo_trmte') > 0) {
            return $this->generar($request);
        }

        $ctx = $this->contextoSesion();
        $intCdgoTpoTrmte = (int) $request->input('intCdgoTpoTrmte', 0);

        $datos = $this->secuencialService->obtenerDatosIndex($ctx['cmpnia'], $ctx['area_id']);

        return view('mod_gene.mod_tram_inter.ver_secuencial_area', [
            'areaNombre' => $ctx['area_nombre'],
            'anios' => $datos['anios'],
            'cdgoAnioActual' => $datos['cdgoAnioActual'],
            'tipos' => $datos['tipos'],
            'intCdgoTpoTrmte' => $intCdgoTpoTrmte,
        ]);
    }

    /**
     * Equivalente a ajax_lista_secuencial_documentos.php
     */
    public function lista(Request $request): JsonResponse
    {
        $cdgoTpo = (int) $request->input('cdgo_tpo_trmte', 0);
        $cdgoAnio = (int) $request->input('cdgo_anio', 0);
        $ctx = $this->contextoSesion();

        if ($cdgoTpo <= 0 || $cdgoAnio <= 0) {
            return response()->json(['html' => '<p class="det-empty">Seleccione año y tipo de documento.</p>']);
        }

        $rows = $this->secuencialService->obtenerListaSecuenciales($cdgoTpo, $cdgoAnio, $ctx['area_id'], $ctx['cargo_id']);

        $html = view('mod_gene.mod_tram_inter.partials.ver_secuencial_area_lista', [
            'rows' => $rows,
        ])->render();

        return response()->json(['html' => $html]);
    }

    private function generar(Request $request): RedirectResponse
    {
        $request->validate([
            'sel_cdgo_tpo_trmte' => 'required|integer|min:1',
            'sel_cdgo_anio' => 'required|integer|min:1',
            'txt_de_scncal_tpo_trmtes_area' => 'nullable|string|max:2000',
        ]);

        $ctx = $this->contextoSesion();
        $cdgoTpo = (int) $request->input('sel_cdgo_tpo_trmte');
        $cdgoAnio = (int) $request->input('sel_cdgo_anio');
        $detalle = mb_strtoupper(trim((string) $request->input('txt_de_scncal_tpo_trmtes_area', '')));
        $userId = (int) (Auth::user()->cdgo_lgin ?? 0);

        $nu = $this->secuencialService->generarSecuencial(
            $cdgoTpo,
            $cdgoAnio,
            $detalle,
            $ctx['area_id'],
            $ctx['cargo_id'],
            $userId
        );

        return redirect()
            ->to(url('/mod_gene/mod_tram_inter/ver_secuencial_area') . '?intCdgoTpoTrmte=' . $cdgoTpo)
            ->with('ok', 'Número generado: ' . $nu);
    }

    private function contextoSesion(): array
    {
        $cargoId = (int) session(SessionKeys::CARGO_ID, 0);
        $cmpnia = (int) session(SessionKeys::USER_COMPANY, 0);
        if ($cmpnia <= 0) {
            abort(403, 'No tiene una compañía activa válida en la sesión.');
        }

        $info = $this->secuencialService->obtenerContextoCargoArea($cargoId);
        $areaId = $info['area_id'];
        $areaNombre = (string) (session(SessionKeys::AREA_NOMBRE) ?: ($info['area_nombre'] ?? ''));

        return [
            'cargo_id' => $cargoId,
            'area_id' => $areaId,
            'area_nombre' => $areaNombre !== '' ? $areaNombre : '—',
            'cmpnia' => $cmpnia,
        ];
    }
}
