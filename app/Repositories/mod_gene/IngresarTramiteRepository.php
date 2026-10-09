<?php
declare(strict_types=1);

namespace App\Repositories\mod_gene;

use App\Contracts\Repositories\mod_gene\IngresarTramiteRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class IngresarTramiteRepository implements IngresarTramiteRepositoryInterface
{
    private string $schema;

    public function __construct()
    {
        $this->schema = rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';
    }

    public function obtenerTiposDocumento(): Collection
    {
        return DB::table($this->schema . 'a_tpo_trmtes')
            ->select('cdgo_tpo_trmte', 'de_tpo_trmte', 'in_frma_dgtal', 'in_scncal', 'in_cc')
            ->orderBy('de_tpo_trmte', 'asc')
            ->get();
    }

    public function obtenerAreasDestino(): Collection
    {
        return DB::table($this->schema . 'a_areas')
            ->select('cdgo_area', 'dc_area', 'de_area', 'in_esta', 'cdgo_tpo_area')
            ->where('in_esta', '1')
            ->orderBy('cdgo_tpo_area', 'asc')
            ->orderBy('de_area', 'asc')
            ->get();
    }

    public function obtenerAnioPorValor(int $anio): ?object
    {
        return DB::table($this->schema . 'a_anios')
            ->where('de_anio', (string)$anio)
            ->first();
    }

    public function obtenerAnioReciente(): ?object
    {
        return DB::table($this->schema . 'a_anios')
            ->orderBy('de_anio', 'desc')
            ->first();
    }

    public function obtenerInfoTipoTramite(int $cdgoTpoTrmte): ?object
    {
        return DB::table($this->schema . 'a_tpo_trmtes')
            ->where('cdgo_tpo_trmte', $cdgoTpoTrmte)
            ->first();
    }

    public function obtenerNuevaSemilla(int $cdgoTpoTrmte, int $cdgoAnio, int $areaId, int $cargoId, int $inScncal): int
    {
        $querySemilla = DB::table($this->schema . 'a_smlla_intrno')
            ->where('cdgo_tpo_trmte', $cdgoTpoTrmte)
            ->where('cdgo_anio', $cdgoAnio)
            ->lockForUpdate();

        if ($inScncal === 1) {
            $querySemilla->where('cdgo_crgo', $cargoId);
        } else {
            $querySemilla->where('cdgo_area', $areaId);
        }

        $maxRow = $querySemilla->orderBy('nu_smlla_intrno', 'desc')->first();
        $maxSemilla = $maxRow ? $maxRow->nu_smlla_intrno : 0;
        return ((int) $maxSemilla) + 1;
    }

    public function guardarSemilla(array $datosSemilla): void
    {
        DB::table($this->schema . 'a_smlla_intrno')->insert($datosSemilla);
    }

    public function registrarNuevoTramite(array $datosTramite): int
    {
        return DB::table($this->schema . 'p_dtos_ntrnos')->insertGetId($datosTramite, 'cdgo_dtos_ntrnos');
    }

    public function actualizarRutaAdjunto(int $idTramite, string $rutaAdjuntos): void
    {
        DB::table($this->schema . 'p_dtos_ntrnos')
            ->where('cdgo_dtos_ntrnos', $idTramite)
            ->update(['de_urls_adjnto' => $rutaAdjuntos]);
    }

    public function obtenerCargosDestinoPorAreas(array $areasDestino, ?int $selCrgoEspecifico = null): Collection
    {
        return DB::table($this->schema . 'p_crgos')
            ->whereIn('cdgo_area', $areasDestino)
            ->get()
            ->groupBy('cdgo_area');
    }

    public function registrarHistoricoDestinos(int $idTramite, array $areasDestino, Collection $cargosDestinoPreload, array $datosComunes): void
    {
        foreach ($areasDestino as $index => $area_destino_id) {
            $cargo_destino_id = null;
            if ($index === 0 && isset($datosComunes['sel_crgos']) && $datosComunes['sel_crgos']) {
                $cargo_destino_id = $datosComunes['sel_crgos'];
            } else {
                $cargosArea = $cargosDestinoPreload->get($area_destino_id);
                if ($cargosArea && $cargosArea->isNotEmpty()) {
                    $cargoPrincipal = $cargosArea->where('nu_orde', '1')->sortByDesc('cdgo_crgo')->first();
                    $cargo_destino_id = $cargoPrincipal ? (int) $cargoPrincipal->cdgo_crgo : (int) $cargosArea->sortByDesc('cdgo_crgo')->first()->cdgo_crgo;
                }
            }

            if (!$cargo_destino_id) {
                throw new \Exception("La gerencia destino con ID {$area_destino_id} no tiene cargos activos asignados.");
            }

            $in_copia = $index === 0 ? '1' : '2';

            DB::table($this->schema . 'r_trmtes_ntrnos')->insert([
                'cdgo_dtos_ntrnos' => $idTramite,
                'cdgo_estdo_trmte' => 1, // Recepcionado/Ingresado
                'cdgo_lgin'        => $datosComunes['cdgo_lgin'],
                'cdgo_crgo_sa'     => $datosComunes['cdgo_crgo_sa'],
                'cdgo_crgo_en'     => $cargo_destino_id,
                'de_urls_agrdo'    => $datosComunes['de_urls_agrdo'],
                'fe_rcbdo'         => $datosComunes['fe_rcbdo'],
                'de_obser_ntrnos'  => $datosComunes['de_obser_ntrnos'],
                'in_copia'         => $in_copia
            ]);
        }
    }

    public function actualizarHistoricoReferencia(int $idTramiteReferente, string $fecha, string $rutaAdjunto): void
    {
        // En base a la lógica original legacy
        DB::table($this->schema . 'r_trmtes_ntrnos')
            ->where('cdgo_trmtes_ntrnos', $idTramiteReferente) // o el ID de r_trmtes_ntrnos que corresponda
            ->update([
                'fe_rcbdo'      => $fecha,
                'de_urls_agrdo' => $rutaAdjunto
            ]);
    }

    public function obtenerDestinatarioPrincipal(int $areaDestinoId): ?object
    {
        return DB::table($this->schema . 'p_crgos as p_crgos')
            ->join($this->schema . 'r_crgos_lgin as r_crgos_lgin', 'r_crgos_lgin.cdgo_crgo', '=', 'p_crgos.cdgo_crgo')
            ->join($this->schema . 'p_lgin as p_lgin', 'r_crgos_lgin.cdgo_lgin', '=', 'p_lgin.cdgo_lgin')
            ->join($this->schema . 'p_usrios as p_usrios', 'p_lgin.cdgo_usrios', '=', 'p_usrios.cdgo_usrios')
            ->select('p_usrios.no_crto', 'p_crgos.de_crgo')
            ->where('p_crgos.cdgo_area', $areaDestinoId)
            ->where('r_crgos_lgin.in_crgo_prmro', '1')
            ->first();
    }

    public function obtenerMultiplesDestinatarios(array $areasDestino): Collection
    {
        return DB::table($this->schema . 'p_crgos as p_crgos')
            ->join($this->schema . 'r_crgos_lgin as r_crgos_lgin', 'r_crgos_lgin.cdgo_crgo', '=', 'p_crgos.cdgo_crgo')
            ->join($this->schema . 'p_lgin as p_lgin', 'r_crgos_lgin.cdgo_lgin', '=', 'p_lgin.cdgo_lgin')
            ->join($this->schema . 'p_usrios as p_usrios', 'p_lgin.cdgo_usrios', '=', 'p_usrios.cdgo_usrios')
            ->select('p_usrios.no_crto', 'p_crgos.de_crgo')
            ->whereIn('p_crgos.cdgo_area', $areasDestino)
            ->where('r_crgos_lgin.in_crgo_prmro', '1')
            ->get();
    }

    public function obtenerNombresAreas(array $areasIds): array
    {
        if (empty($areasIds)) {
            return [];
        }

        $areas = DB::table($this->schema . 'a_areas')
            ->whereIn('cdgo_area', $areasIds)
            ->select('cdgo_area', 'dc_area')
            ->get()
            ->keyBy('cdgo_area');

        $result = [];
        foreach ($areasIds as $id) {
            if (isset($areas[$id]) && !empty($areas[$id]->dc_area)) {
                $result[] = $areas[$id]->dc_area;
            }
        }

        return $result;
    }

    public function obtenerJerarquiaArea(int $areaId): ?object
    {
        return DB::table($this->schema . 'a_areas as a_areas')
            ->leftJoin($this->schema . 'a_areas as a_areas_prim', 'a_areas.cdgo_area_prim', '=', 'a_areas_prim.cdgo_area')
            ->select('a_areas.dc_area', 'a_areas_prim.dc_area as dc_area_prim')
            ->where('a_areas.cdgo_area', $areaId)
            ->first();
    }
}
