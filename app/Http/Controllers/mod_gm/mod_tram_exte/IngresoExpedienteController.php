<?php

declare(strict_types=1);

namespace App\Http\Controllers\mod_gm\mod_tram_exte;

use App\Contracts\Repositories\mod_gm\mod_tram_exte\IngresoExpedienteRepositoryInterface;
use App\Contracts\Services\mod_gm\mod_tram_exte\PdfPageCounterInterface;
use App\Exceptions\mod_gm\PdfPageCounterUnavailableException;
use App\Exceptions\mod_gm\PdfValidationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\IngresoExpedienteRequest;
use App\Models\User;
use App\Services\IngresoExpedienteService;
use App\Support\SessionKeys;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class IngresoExpedienteController extends Controller
{
    private readonly PdfPageCounterInterface $pdfCounter;

    public function __construct(
        private readonly IngresoExpedienteService $service,
        private readonly IngresoExpedienteRepositoryInterface $repository,
        ?PdfPageCounterInterface $pdfCounter = null
    ) {
        $this->pdfCounter = $pdfCounter ?? app(PdfPageCounterInterface::class);
    }

    /**
     * Vista de Ingreso de Expedientes.
     * El Blade se toma de a_rles.de_dreccon (no de un nombre fijo).
     */
    public function index(?Request $request = null, ?string $pathVista = null, ?object $opcionMenu = null)
    {
        $pathVista = $this->vistaDesdeMenu($pathVista, $opcionMenu);
        $tituloOpcion = trim((string) ($opcionMenu !== null ? ($opcionMenu->dc_rle ?? 'Ingresar Expedientes') : 'Ingresar Expedientes'));
        if ($tituloOpcion === '') {
            $tituloOpcion = 'Ingresar Expedientes';
        }

        $cmpnia = $this->resolverCompaniaActiva();
        $datosForm = $this->repository->obtenerDatosFormularioIngreso($cmpnia);

        $gerencias = $datosForm['gerencias'];
        $servicios = $datosForm['servicios'];
        $tiposDocumento = $datosForm['tiposDocumento'];
        $tiposIngreso = $datosForm['tiposIngreso'];
        $tipoExterno = $datosForm['tipoExterno'];
        $tipoSimple = $datosForm['tipoSimple'];
        $tipoObservado = $datosForm['tipoObservado'];
        $areaObservado = $datosForm['areaObservado'];

        $fechaServidor = now()->format('d/m/Y H:i:s');

        return view($pathVista, compact(
            'gerencias',
            'servicios',
            'tiposDocumento',
            'tiposIngreso',
            'tipoExterno',
            'tipoSimple',
            'tipoObservado',
            'areaObservado',
            'fechaServidor',
            'tituloOpcion',
            'pathVista'
        ));
    }

    public function store(IngresoExpedienteRequest $request)
    {
        try {
            /** @var User|null $user */
            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sesión no válida o expirada.',
                ], 401);
            }

            $validated = $request->validated();
            $validated['fecha_ingreso'] = now()->format('Y-m-d H:i:s');
            // Pasar valores crudos al repository únicamente para verificación de no manipulación
            $validated['cdgo_tpo_observado'] = (int) $request->input('cdgo_tpo_observado', 0);
            $validated['cdgo_tpo_trmte'] = (int) $request->input('cdgo_tpo_trmte', 0);
            $validated['cdgo_area_observado'] = (int) $request->input('cdgo_area_observado', 0);
            $validated['in_docu_trmte'] = (string) ($validated['in_docu_trmte'] ?? '');

            $archivo = $request->file('fle_adjunto');
            if ($archivo instanceof \Illuminate\Http\UploadedFile && $archivo->isValid()) {
                try {
                    $realFolios = $this->pdfCounter->countPages($archivo);
                    $validated['folios'] = $realFolios;
                } catch (PdfValidationException $e) {
                    return response()->json([
                        'success' => false,
                        'message' => 'El archivo PDF adjunto no es válido: ' . $e->getMessage(),
                    ], 422);
                } catch (PdfPageCounterUnavailableException $e) {
                    Log::warning('Conteo automático con pdfinfo no disponible durante store; conservando folios ingresados.', [
                        'folios' => $validated['folios'] ?? null,
                    ]);
                }
            }

            $companiaId = $this->resolverCompaniaActiva();

            $result = $this->service->registrar(
                $validated,
                $user,
                $companiaId,
                (string) $request->ip(),
                $archivo
            );

            return response()->json([
                'success' => true,
                'message' => 'Expediente registrado correctamente con el número: ' . $result['numero'],
                'expediente_id' => $result['id'],
                'numero_expediente' => $result['numero'],
                'folios' => (int) ($validated['folios'] ?? 0),
                'voucher_url' => route('tramites.voucher', ['id' => $result['id']], false),
                'show_url' => route('tramites.busca_expedi', ['cdgo_tram' => $result['id']], false),
                'inbox_url' => route('tramites.bandeja', [], false),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Illuminate\Auth\Access\AuthorizationException | \App\Exceptions\mod_gene\OperacionTramiteNoAutorizadaException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            $errorId = Str::uuid()->toString();
            Log::error('Error registrando expediente: ' . $e->getMessage(), [
                'error_id' => $errorId,
                'trace' => $e->getTraceAsString(),
                'input' => $request->except(['fle_adjunto']),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Hubo un error al registrar el expediente.',
                'error_id' => $errorId,
            ], 500);
        }
    }

    public function buscarUsuario(Request $request)
    {
        $tipo = $request->query('tipo');
        $documento = preg_replace('/[^0-9]/', '', (string) ($request->query('documento') ?? ''));

        if ($tipo !== 'juridica' && $tipo !== 'natural') {
            return response()->json(['success' => false, 'message' => 'Tipo de persona inválido.'], 400);
        }

        if (empty($documento)) {
            return response()->json([
                'success' => false,
                'message' => 'El número de documento es obligatorio y debe contener solo números.',
            ], 400);
        }

        $cmpnia = $this->resolverCompaniaActiva();

        try {
            if ($tipo === 'juridica') {
                $usr = $this->repository->buscarUsuarioJuridico($documento, $cmpnia);

                if (!$usr) {
                    return response()->json([
                        'success' => false,
                        'message' => 'RUC no encontrado en el sistema.',
                    ]);
                }

                return response()->json([
                    'success' => true,
                    'data' => $usr,
                ]);
            }

            $usr = $this->repository->buscarUsuarioNatural($documento, $cmpnia);

            if (!$usr) {
                return response()->json([
                    'success' => false,
                    'message' => 'DNI/Documento no encontrado en el sistema.',
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $usr,
            ]);
        } catch (\Throwable $e) {
            $errorId = Str::uuid()->toString();
            Log::error('Error al buscar el solicitante: ' . $e->getMessage(), [
                'error_id' => $errorId,
                'documento' => $documento,
                'tipo' => $tipo,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al buscar el solicitante.',
                'error_id' => $errorId,
            ], 500);
        }
    }

    public function buscarJuridico(Request $request)
    {
        $razon = trim(strip_tags((string) $request->query('razon', '')));
        if (mb_strlen($razon) < 3) {
            return response()->json(['success' => false, 'message' => 'Ingrese al menos 3 caracteres.']);
        }

        $cmpnia = $this->resolverCompaniaActiva();
        $usr = $this->repository->buscarUsuarioJuridicoPorRazon($razon, $cmpnia);

        if (!$usr) {
            return response()->json(['success' => false, 'message' => 'Razón social no encontrada.']);
        }

        return response()->json([
            'success' => true,
            'data' => $usr,
        ]);
    }

    public function sugerirJuridicos(Request $request)
    {
        $term = trim(strip_tags((string) $request->query('term', '')));
        if (mb_strlen($term) < 3) {
            return response()->json([]);
        }

        $cmpnia = $this->resolverCompaniaActiva();
        $items = $this->repository->sugerirUsuariosJuridicos($term, $cmpnia, 20);

        return response()->json($items);
    }

    public function listarSubgerencias($gerenciaId)
    {
        $cmpnia = $this->resolverCompaniaActiva();
        $subgerencias = $this->repository->listarSubgerencias((int) $gerenciaId, $cmpnia);

        return response()->json($subgerencias);
    }

    public function listarTramites($servicioId)
    {
        $cmpnia = $this->resolverCompaniaActiva();
        $tramites = $this->repository->listarTramitesPorServicio((int) $servicioId, $cmpnia);

        return response()->json($tramites);
    }

    public function listarRequisitos($tramiteId)
    {
        $cmpnia = $this->resolverCompaniaActiva();
        $data = $this->repository->listarRequisitosPorTramite((int) $tramiteId, $cmpnia);

        return response()->json([
            'requisitos' => $data['requisitos'],
            'silencio' => $data['silencio'],
        ]);
    }

    public function buscarTramitesTupa(Request $request)
    {
        $term = strip_tags(trim((string) $request->query('term', '')));
        if (strlen($term) < 3) {
            return response()->json([]);
        }

        $cmpnia = $this->resolverCompaniaActiva();
        $tramites = $this->repository->buscarTramitesTupa($term, $cmpnia, 20);

        return response()->json($tramites);
    }

    public function lista(Request $request)
    {
        $cmpnia = $this->resolverCompaniaActiva();
        $pagina = max(1, (int) $request->query('pagina', 1));
        $tamPag = 20;

        $filtros = [
            'modo' => (string) $request->query('modo', 'generico'),
            'criterio' => trim((string) $request->query('criterio', '')),
            'campo' => (string) $request->query('campo', 'p_dto_trmtes.nu_expe_todo'),
            'fecha' => trim((string) $request->query('fecha', '')),
            'desde' => trim((string) $request->query('desde', '')),
            'hasta' => trim((string) $request->query('hasta', '')),
        ];

        $resultado = $this->repository->listarExpedientes($filtros, $cmpnia, $pagina, $tamPag);

        $rows = $resultado['items']->map(function ($row) {
            $row->nu_expe_fmt = $this->formatExpediente((string) $row->nu_expe_todo);
            $row->fecha_dm = $row->fe_ingr_trmte
                ? Carbon::parse($row->fe_ingr_trmte)->format('d/m')
                : '';
            $row->silencio = ((string) $row->in_sile_admi === '1') ? 'Sí' : 'No';
            return $row;
        });

        return response()->json([
            'success' => true,
            'total' => $resultado['total'],
            'pagina' => $resultado['pagina'],
            'paginas' => $resultado['paginas'],
            'items' => $rows,
        ]);
    }

    public function contarFolios(Request $request)
    {
        $file = $request->file('fle_adjunto');
        if (!$file instanceof \Illuminate\Http\UploadedFile) {
            return response()->json([
                'success' => false,
                'message' => 'Archivo PDF requerido para el conteo de folios.',
                'folios' => 0,
            ], 422);
        }

        try {
            $folios = $this->pdfCounter->countPages($file);

            return response()->json([
                'success' => true,
                'folios' => $folios,
                'source' => 'pdfinfo',
            ]);
        } catch (PdfValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'folios' => 0,
            ], 422);
        } catch (PdfPageCounterUnavailableException $e) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo determinar automáticamente el número de páginas. Verifique el PDF o comuníquese con soporte',
                'folios' => 0,
            ], 503);
        }
    }

    public function voucher($id)
    {
        $cmpnia = $this->resolverCompaniaActiva();

        $data = $this->repository->obtenerVoucherExpediente((int) $id, $cmpnia);
        if (!$data) {
            abort(404, 'Expediente no encontrado.');
        }

        return view('mod_gm.mod_tram_exte.voucher', $data);
    }

    /**
     * Resuelve de forma estricta la compañía activa del usuario o sesión.
     */
    private function resolverCompaniaActiva(): int
    {
        $cmpnia = (int) session(SessionKeys::USER_COMPANY, 0);
        if ($cmpnia <= 0) {
            abort(403, 'No tiene una compañía activa válida en la sesión para registrar trámites.');
        }

        return $cmpnia;
    }

    private function formatExpediente(string $nu): string
    {
        $parts = explode('-', $nu);
        if (count($parts) < 3) {
            return $nu;
        }

        $mid = $parts[1] . '-' . substr($parts[2], 2, 10);

        return $parts[0] . '-' . str_pad($mid, 9, '0', STR_PAD_LEFT);
    }
}
