<?php

namespace App\Http\Controllers\mod_ti\dispositivo_seg;

use App\Contracts\Services\SecurityCoreInterface;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\mod_ti\DispositivoSegService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class DispositivoSegController extends Controller
{
    public function __construct(
        private readonly DispositivoSegService $service,
        private readonly SecurityCoreInterface $securityCore
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $user = Auth::user();
        if (!$user instanceof User) {
            return redirect()->route('login');
        }

        $cargoId = (int) $request->session()->get('sigtram_cargo_id', 0);
        if ($cargoId <= 0) {
            return redirect()->route('seleccionar.cargo');
        }

        $this->assertPermiso($user, $cargoId);

        return view('mod_ti.dispositivo_seg.index', [
            'routes' => $this->apiRoutes(),
        ]);
    }

    public function mapa(Request $request): View|RedirectResponse
    {
        $user = Auth::user();
        if (!$user instanceof User) {
            return redirect()->route('login');
        }
        $cargoId = (int) $request->session()->get('sigtram_cargo_id', 0);
        if ($cargoId <= 0) {
            return redirect()->route('seleccionar.cargo');
        }
        $this->assertPermiso($user, $cargoId);
        $f = $this->filtrosFromRequest($request);

        return view('mod_ti.dispositivo_seg.mapa', [
            'routes' => $this->apiRoutes(),
            'filtros' => $f,
        ]);
    }

    public function graficos(Request $request): View|RedirectResponse
    {
        $user = Auth::user();
        if (!$user instanceof User) {
            return redirect()->route('login');
        }
        $cargoId = (int) $request->session()->get('sigtram_cargo_id', 0);
        if ($cargoId <= 0) {
            return redirect()->route('seleccionar.cargo');
        }
        $this->assertPermiso($user, $cargoId);
        $f = $this->filtrosFromRequest($request);

        return view('mod_ti.dispositivo_seg.graficos', [
            'routes' => $this->apiRoutes(),
            'filtros' => $f,
        ]);
    }

    public function listar(Request $request): JsonResponse
    {
        if (!$this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $f = $this->filtrosFromRequest($request);
        $resultado = $this->service->listar(
            (int) $request->input('page', 1),
            (int) $request->input('per_page', 10),
            $f['q'],
            $f['tipo'],
            $f['zona'],
            $f['estado'],
            $f['fecha_desde'],
            $f['fecha_hasta'],
            $f['sector_id'],
            $f['sub_sector_id']
        );

        return response()->json($resultado, $resultado['success'] ? 200 : 500);
    }

    public function detalle(Request $request): JsonResponse
    {
        if (!$this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $id = (int) $request->input('id', 0);
        if ($id <= 0) {
            return response()->json(['success' => false, 'error' => 'ID no valido.'], 400);
        }

        $resultado = $this->service->obtener($id);

        return response()->json($resultado, $resultado['success'] ? 200 : ($resultado['error'] === 'Registro no encontrado.' ? 404 : 500));
    }

    public function guardar(Request $request): JsonResponse
    {
        if (!$this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $datos = $request->all();
        $resultado = $this->service->guardar($datos);

        return response()->json($resultado, $resultado['success'] ? 200 : 400);
    }

    public function actualizar(Request $request): JsonResponse
    {
        if (!$this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $datos = $request->all();
        $resultado = $this->service->actualizar($datos);

        return response()->json($resultado, $resultado['success'] ? 200 : 400);
    }

    public function eliminar(Request $request): JsonResponse
    {
        if (!$this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $id = (int) ($request->input('id', 0));
        if ($id <= 0) {
            return response()->json(['success' => false, 'error' => 'ID no valido.'], 400);
        }

        $resultado = $this->service->eliminar($id);

        return response()->json($resultado, $resultado['success'] ? 200 : 400);
    }

    public function sectores(Request $request): JsonResponse
    {
        if (!$this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $resSectores = $this->service->listarSectores();
        $resSubSectores = $this->service->listarSubSectores();

        return response()->json([
            'success' => true,
            'data' => [
                'sectores' => $resSectores['data'] ?? [],
                'sub_sectores' => $resSubSectores['data'] ?? [],
            ],
        ]);
    }

    public function subsectores(Request $request): JsonResponse
    {
        if (!$this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $sectorId = (int) $request->input('sector_id', 0);
        $resultado = $this->service->listarSubSectoresPorSector($sectorId);

        return response()->json([
            'success' => true,
            'data' => $resultado['data'] ?? [],
        ]);
    }

    public function exportar(Request $request): Response|BinaryFileResponse|JsonResponse
    {
        if (!$this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $f = $this->filtrosFromRequest($request);
        $resultado = $this->service->exportar(
            $f['q'],
            $f['tipo'],
            $f['zona'],
            $f['estado'],
            $f['fecha_desde'],
            $f['fecha_hasta'],
            $f['sector_id'],
            $f['sub_sector_id']
        );

        if (!$resultado['success']) {
            return response($resultado['error'] ?? 'Error', 500);
        }

        $columnas = [
            ['key' => 'id', 'label' => 'ID', 'width' => 8, 'num' => true],
            ['key' => 'tipo_dispositivo', 'label' => 'Tipo Dispositivo', 'width' => 18],
            ['key' => 'item', 'label' => 'Item', 'width' => 8, 'num' => true],
            ['key' => 'zona', 'label' => 'Zona', 'width' => 12],
            ['key' => 'sector', 'label' => 'Sector', 'width' => 20],
            ['key' => 'sub_sector', 'label' => 'Sub Sector', 'width' => 20],
            ['key' => 'codigo_kmz', 'label' => 'Codigo KMZ', 'width' => 15],
            ['key' => 'nuevo_codigo', 'label' => 'Nuevo Codigo', 'width' => 15],
            ['key' => 'direccion', 'label' => 'Direccion', 'width' => 40],
            ['key' => 'latitud', 'label' => 'Latitud', 'width' => 14],
            ['key' => 'longitud', 'label' => 'Longitud', 'width' => 14],
            ['key' => 'url', 'label' => 'URL Mapa', 'width' => 45],
            ['key' => 'ip', 'label' => 'IP', 'width' => 16],
            ['key' => 'marca', 'label' => 'Marca', 'width' => 15],
            ['key' => 'modelo', 'label' => 'Modelo', 'width' => 25],
            ['key' => 'tipo', 'label' => 'Tipo', 'width' => 12],
            ['key' => 'resolucion', 'label' => 'Resolucion', 'width' => 14],
            ['key' => 'megapixeles', 'label' => 'Megapixeles', 'width' => 12, 'num' => true],
            ['key' => 'zoom', 'label' => 'Zoom', 'width' => 14],
            ['key' => 'tecnologia', 'label' => 'Tecnologia', 'width' => 14],
            ['key' => 'anio_instalacion', 'label' => 'Año Instalacion', 'width' => 16],
            ['key' => 'conexion', 'label' => 'Conexion', 'width' => 16],
            ['key' => 'estado', 'label' => 'Estado', 'width' => 15],
            ['key' => 'observaciones', 'label' => 'Observaciones', 'width' => 30],
            ['key' => 'vcs', 'label' => 'VCS', 'width' => 8, 'num' => true],
            ['key' => 'ultima_conexion', 'label' => 'Ultima Conexion', 'width' => 22],
            ['key' => 'numero_oc_contrato', 'label' => 'Nro OC/Contrato', 'width' => 18],
            ['key' => 'fecha_contrato', 'label' => 'Fecha Contrato', 'width' => 16],
            ['key' => 'proveedor', 'label' => 'Proveedor', 'width' => 20],
        ];

        $filename = 'dispositivos_seguridad_' . date('Ymd_His') . '.xlsx';
        $tmp = $this->generarXlsx($columnas, $resultado['data'], $filename);

        return response()->download($tmp, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function estadisticas(Request $request): JsonResponse
    {
        if (!$this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }
        $f = $this->filtrosFromRequest($request);
        $resultado = $this->service->estadisticas(
            $f['q'], $f['tipo'], $f['zona'], $f['estado'],
            $f['fecha_desde'], $f['fecha_hasta'], $f['sector_id'], $f['sub_sector_id']
        );

        return response()->json($resultado, $resultado['success'] ? 200 : 500);
    }

    public function ping(Request $request): JsonResponse
    {
        if (!$this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $id = (int) $request->input('id', 0);
        if ($id <= 0) {
            return response()->json(['success' => false, 'error' => 'ID de dispositivo requerido.'], 400);
        }

        $obtener = $this->service->obtener($id);
        if (!$obtener['success'] || empty($obtener['data'])) {
            return response()->json(['success' => false, 'error' => 'Dispositivo no encontrado.'], 404);
        }

        $ip = trim((string) ($obtener['data']['ip'] ?? ''));
        if ($ip === '') {
            return response()->json(['success' => false, 'error' => 'El dispositivo no tiene IP configurada.'], 400);
        }

        $ipSinMascara = explode('/', $ip)[0];
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $cmd = 'ping -n 1 -w 2000 ' . escapeshellarg($ipSinMascara);
        } else {
            $cmd = 'ping -c 1 -W 2 ' . escapeshellarg($ipSinMascara);
        }
        $salida = [];
        $codigo = 1;
        @exec($cmd . ' 2>&1', $salida, $codigo);
        $alcanzable = ($codigo === 0);
        $nuevoEstado = $alcanzable ? 'OPERATIVO' : 'INOPERATIVO';
        $resultado = $this->service->actualizarEstado($id, $nuevoEstado, $alcanzable);

        return response()->json([
            'success' => true,
            'ping_ok' => $alcanzable,
            'estado' => $nuevoEstado,
            'mensaje' => $alcanzable
                ? ($resultado['success'] ? 'Dispositivo operativo. Estado actualizado.' : 'Dispositivo alcanzable.')
                : ($resultado['success'] ? 'Dispositivo inoperativo. Estado actualizado.' : 'Dispositivo no responde al ping.'),
            'actualizado_bd' => (bool) ($resultado['success'] ?? false),
            'error_bd' => $resultado['error'] ?? '',
        ]);
    }

    public function zonasGeojson(Request $request): JsonResponse
    {
        if (!$this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }

        return response()->json($this->service->geojsonZonas());
    }

    public function sectoresGeojson(Request $request): JsonResponse
    {
        if (!$this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }

        return response()->json($this->service->geojsonSectores());
    }

    public function subsectoresGeojson(Request $request): JsonResponse
    {
        if (!$this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }

        return response()->json($this->service->geojsonSubsectores());
    }

    public function reporteVisores(Request $request): JsonResponse
    {
        if (!$this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }
        $resultado = $this->service->reporteVisores();

        return response()->json($resultado, $resultado['success'] ? 200 : 500);
    }

    // ── Auth / filtros ────────────────────────────────────────────────────

    private function apiRoutes(): array
    {
        return [
            'listar' => route('dispositivo_seg.listar'),
            'detalle' => route('dispositivo_seg.detalle'),
            'guardar' => route('dispositivo_seg.guardar'),
            'actualizar' => route('dispositivo_seg.actualizar'),
            'eliminar' => route('dispositivo_seg.eliminar'),
            'sectores' => route('dispositivo_seg.sectores'),
            'subsectores' => route('dispositivo_seg.subsectores'),
            'exportar' => route('dispositivo_seg.exportar'),
            'estadisticas' => route('dispositivo_seg.estadisticas'),
            'ping' => route('dispositivo_seg.ping'),
            'zonas_geojson' => route('dispositivo_seg.zonas_geojson'),
            'sectores_geojson' => route('dispositivo_seg.sectores_geojson'),
            'subsectores_geojson' => route('dispositivo_seg.subsectores_geojson'),
            'reporte_visores' => route('dispositivo_seg.reporte_visores'),
            'stream' => url('/mod_ti/dispositivo_seg/stream'),
            'tail_log' => route('dispositivo_seg.tail_log'),
            'mapa' => route('dispositivo_seg.mapa'),
            'graficos' => route('dispositivo_seg.graficos'),
            'index' => route('dispositivo_seg.index'),
            'inicio' => route('inicio'),
            'asset_image' => asset('mod_ti/dispositivo_seg/image'),
        ];
    }

    private function checkAccess(Request $request): bool
    {
        $user = Auth::user();
        if (!$user instanceof User) {
            return false;
        }
        $cargoId = (int) $request->session()->get('sigtram_cargo_id', 0);
        if ($cargoId <= 0) {
            return false;
        }

        return $this->tienePermiso($user, $cargoId);
    }

    private function assertPermiso(User $user, int $cargoId): void
    {
        if (!$this->tienePermiso($user, $cargoId)) {
            abort(403, 'No tienes permiso para acceder a este módulo.');
        }
    }

    private function tienePermiso(User $user, int $cargoId): bool
    {
        return $this->securityCore->tienePermiso((int) $user->cdgo_lgin, '/mod_ti/dispositivo_seg', $cargoId)
            || $this->securityCore->tienePermiso((int) $user->cdgo_lgin, '/mod_ti/dispositivo_seg/index', $cargoId);
    }

    private function filtrosFromRequest(Request $request): array
    {
        $sectorId = (int) $request->input('sector_id', 0);
        $subSectorId = (int) $request->input('sub_sector_id', 0);

        return [
            'q' => trim((string) $request->input('q', '')),
            'tipo' => trim((string) $request->input('tipo_dispositivo', '')),
            'zona' => trim((string) $request->input('zona', '')),
            'estado' => trim((string) $request->input('estado', '')),
            'fecha_desde' => trim((string) $request->input('fecha_desde', '')),
            'fecha_hasta' => trim((string) $request->input('fecha_hasta', '')),
            'sector_id' => $sectorId > 0 ? $sectorId : null,
            'sub_sector_id' => $subSectorId > 0 ? $subSectorId : null,
            'sector_id_raw' => $sectorId > 0 ? (string) $sectorId : '',
            'sub_sector_id_raw' => $subSectorId > 0 ? (string) $subSectorId : '',
        ];
    }

    // ── XLSX nativo ───────────────────────────────────────────────────────

    private function generarXlsx(array $columnas, array $items, string $filename): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            abort(500, 'No se pudo generar el archivo.');
        }

        $zip->addFromString('[Content_Types].xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>'
        );

        $zip->addFromString('_rels/.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>'
        );

        $zip->addFromString('xl/_rels/workbook.xml.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>'
        );

        $zip->addFromString('xl/workbook.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Dispositivos" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>'
        );

        $zip->addFromString('xl/styles.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2">'
            . '<font><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            . '</fonts>'
            . '<fills count="3">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFDC2626"/></patternFill></fill>'
            . '</fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="2">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
            . '</cellXfs>'
            . '</styleSheet>'
        );

        $numCols = count($columnas);
        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<cols>';

        for ($c = 0; $c < $numCols; $c++) {
            $w = $columnas[$c]['width'];
            $sheet .= '<col min="' . ($c + 1) . '" max="' . ($c + 1) . '" width="' . $w . '" customWidth="1"/>';
        }

        $sheet .= '</cols><sheetData>';
        $sheet .= '<row r="1">';
        for ($c = 0; $c < $numCols; $c++) {
            $ref = $this->colLetter($c) . '1';
            $sheet .= '<c r="' . $ref . '" t="inlineStr" s="1"><is><t>' . $this->escXml($columnas[$c]['label']) . '</t></is></c>';
        }
        $sheet .= '</row>';

        $r = 2;
        foreach ($items as $row) {
            $sheet .= '<row r="' . $r . '">';
            for ($c = 0; $c < $numCols; $c++) {
                $ref = $this->colLetter($c) . $r;
                $key = $columnas[$c]['key'];
                $val = $row[$key] ?? '';
                $esNum = !empty($columnas[$c]['num']);
                if ($esNum && $val !== '') {
                    $sheet .= '<c r="' . $ref . '"><v>' . intval($val) . '</v></c>';
                } else {
                    $sheet .= '<c r="' . $ref . '" t="inlineStr"><is><t>' . $this->escXml($val) . '</t></is></c>';
                }
            }
            $sheet .= '</row>';
            $r++;
        }

        $sheet .= '</sheetData></worksheet>';
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
        $zip->close();

        return $tmp;
    }

    private function escXml(mixed $str): string
    {
        return htmlspecialchars((string) $str, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function colLetter(int $i): string
    {
        $l = '';
        while ($i >= 0) {
            $l = chr(65 + ($i % 26)) . $l;
            $i = intdiv($i, 26) - 1;
        }

        return $l;
    }
}
