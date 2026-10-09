<?php

namespace App\Http\Controllers;

use App\Models\User;
// use App\Support\SigtramPassword; // migración SHA1→bcrypt (desactivada por ahora)
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
// use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Contracts\Services\SecurityCoreInterface;

class AuthController extends Controller
{
    /**
     * @var SecurityCoreInterface
     */
    protected SecurityCoreInterface $securityCore;

    /**
     * AuthController constructor.
     *
     * @param SecurityCoreInterface $securityCore
     */
    public function __construct(SecurityCoreInterface $securityCore)
    {
        $this->securityCore = $securityCore;
    }

    public function seleccionarCargo(Request $request)
    {
        $user = Auth::user();
        if (!$user instanceof User) {
            return redirect()->route('login');
        }

        $idLogin = (int) $user->cdgo_lgin;
        $cargos = $this->securityCore->obtenerCargosUsuario($idLogin);

        if ($cargos->isEmpty()) {
            throw ValidationException::withMessages([
                'usuario' => 'No tiene cargos asignados para ingresar al sistema.',
            ]);
        }

        if ($cargos->count() === 1) {
            $cargo = $cargos->first();
            $request->session()->put('sigtram_cargo_id', (int) $cargo->cdgo_crgo);
            $request->session()->put('sigtram_cargo_nombre', (string) $cargo->de_crgo);
            $request->session()->put('sigtram_area_nombre', (string) ($cargo->de_area ?? ''));

            return redirect()->route('inicio');
        }

        return view('seleccionar-cargo', [
            'cargos' => $cargos,
            'usuario' => $user->nombreMostrar(),
        ]);
    }

    public function guardarCargo(Request $request)
    {
        $user = Auth::user();
        if (!$user instanceof User) {
            return redirect()->route('login');
        }

        $data = $request->validate([
            'cargo_id' => ['required', 'integer'],
        ]);

        $idLogin = (int) $user->cdgo_lgin;
        $cargoId = (int) $data['cargo_id'];

        $cargos = $this->securityCore->obtenerCargosUsuario($idLogin);
        $cargo = $cargos->firstWhere('cdgo_crgo', $cargoId);

        if ($cargo === null) {
            throw ValidationException::withMessages([
                'cargo_id' => 'El cargo seleccionado no es valido para este usuario.',
            ]);
        }

        $request->session()->put('sigtram_cargo_id', (int) $cargo->cdgo_crgo);
        $request->session()->put('sigtram_cargo_nombre', (string) $cargo->de_crgo);
        $request->session()->put('sigtram_area_nombre', (string) ($cargo->de_area ?? ''));

        return redirect()->route('inicio');
    }

    public function inicio()
    {
        $user = Auth::user();
        if (!$user instanceof User) {
            return redirect()->route('login');
        }

        $idLogin = (int) $user->cdgo_lgin;
        $cargoId = (int) request()->session()->get('sigtram_cargo_id', 0);

        if ($cargoId <= 0) {
            return redirect()->route('seleccionar.cargo');
        }

        $cargos = $this->securityCore->obtenerCargosUsuario($idLogin);
        $cargo = $cargos->firstWhere('cdgo_crgo', $cargoId);

        if ($cargo === null) {
            request()->session()->forget(['sigtram_cargo_id', 'sigtram_cargo_nombre', 'sigtram_area_nombre']);

            return redirect()->route('seleccionar.cargo');
        }

        $menuItems = $this->securityCore->obtenerMenuUsuario($idLogin, $cargoId);

        return view('inicio', [
            'menuItems' => $menuItems,
            'area' => $cargo->de_area ?: '-',
            'cargo' => $cargo->de_crgo ?: '-',
            'usuario' => $user->nombreMostrar(),
        ]);
    }

    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('inicio');
        }

        return view('login');
    }

    /**
     * Misma regla que el sigtram antiguo (index.php + seguridad.class.php):
     * SHA1 de la contrasena, tablas sistema.p_lgin y sistema.p_usrios, in_esta = 1.
     *
     * TODO (migración gradual a bcrypt): usar SigtramPassword::check(), y si
     * needs_rehash, actualizar de_pass con SigtramPassword::hash($pwdPlain).
     * Requiere de_pass varchar(60+). Ver app/Support/SigtramPassword.php
     */
    public function login(Request $request)
    {
        $request->validate([
            'usuario' => ['required', 'string'],
            'password' => ['required', 'string'],
        ], [
            'usuario.required' => 'Debe ingresar su usuario.',
            'password.required' => 'Debe ingresar su contrasena.',
        ]);

        $usu = $this->limpiarCampo((string) $request->input('usuario'));
        $pwdPlain = (string) $request->input('password');
        $pwdSha1 = sha1($pwdPlain);

        if (strlen($usu) === 0 || strlen($pwdPlain) === 0) {
            throw ValidationException::withMessages([
                'usuario' => 'El usuario y contrasena no coinciden.',
            ]);
        }

        $schema = rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';
        $plgin = $schema . 'p_lgin';
        $pusrios = $schema . 'p_usrios';

        $row = DB::table($plgin . ' as p_lgin')
            ->join($pusrios . ' as p_usrios', 'p_lgin.cdgo_usrios', '=', 'p_usrios.cdgo_usrios')
            ->where('p_lgin.in_esta', '1')
            ->where('p_lgin.de_lgin', $usu)
            ->where('p_lgin.de_pass', $pwdSha1)
            ->select('p_lgin.cdgo_lgin', 'p_lgin.cdgo_prfle', 'p_usrios.no_crto')
            ->first();

        /*
        // --- Migración SHA1 → bcrypt (DESACTIVADA) ---
        // Buscar usuario sin filtrar por de_pass, luego:
        // $check = SigtramPassword::check($pwdPlain, (string) ($row->de_pass ?? ''));
        // if (!$check['ok']) { ... error ... }
        // if ($check['needs_rehash']) {
        //     DB::table($plgin)->where('cdgo_lgin', $row->cdgo_lgin)
        //         ->update(['de_pass' => SigtramPassword::hash($pwdPlain)]);
        // }
        */

        if ($row === null) {
            throw ValidationException::withMessages([
                'usuario' => 'El usuario y contrasena no coinciden.',
            ]);
        }

        if ((int) $row->cdgo_prfle === (int) config('auth.sigtram_perfil_bloqueado', 99)) {
            throw ValidationException::withMessages([
                'usuario' => 'Usted no tiene accesos en el SISTEMA DE GESTION DE TRAMITE MUNICIPAL',
            ]);
        }

        $user = User::query()->find($row->cdgo_lgin);
        if ($user === null) {
            throw ValidationException::withMessages([
                'usuario' => 'El usuario y contrasena no coinciden.',
            ]);
        }

        // p_lgin no usa remember_token como el login de Laravel por defecto.
        Auth::login($user, false);

        $request->session()->regenerate();
        $request->session()->forget(['sigtram_cargo_id', 'sigtram_cargo_nombre', 'sigtram_area_nombre']);

        //return redirect()->intended(route('seleccionar.cargo'));
        return redirect()->route('seleccionar.cargo');

    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * Equivalente ligero a mos_limpiador_campo_libre del antiguo.
     */
    private function limpiarCampo(string $value): string
    {
        return trim($value);
    }

    private function normalizarDireccion(?string $ruta): string
    {
        if (!is_string($ruta) || trim($ruta) === '') {
            return '#';
        }

        $ruta = trim($ruta);
        if (Str::startsWith($ruta, ['http://', 'https://', '/', '#'])) {
            return $ruta;
        }

        return '/' . ltrim($ruta, '/');
    }




    public function cargarModulo(Request $request, $any)
    {
    // 1. NUEVA VALIDACIÓN: Ignorar si están buscando un archivo estático (.png, .jpg, .css, etc.)
    if (preg_match('/\.(jpg|jpeg|png|gif|svg|ico|css|js)$/i', $any)) {
        abort(404, "Archivo no encontrado.");
    }

    $user = Auth::user();
    $perfil = (int) $user->cdgo_prfle;
    $cargoId = (int) $request->session()->get('sigtram_cargo_id', 0);

    // Permiso: localiza la opción en BD aunque de_dreccon tenga .php o .blade.php
    $rutaLimpia = preg_replace('/\.blade\.php$/i', '', $any) ?? $any;
    $rutaLimpia = preg_replace('/\.php$/i', '', $rutaLimpia) ?? $rutaLimpia;
    $rutaLimpia = '/' . ltrim($rutaLimpia, '/');

    $tienePermiso = $this->securityCore->tienePermiso((int) $user->cdgo_lgin, $rutaLimpia, $cargoId);

    if (!$tienePermiso) {
        abort(403, "No tienes permiso para acceder a este módulo.");
    }

    $opcionMenu = $this->securityCore->obtenerOpcionPorRuta($rutaLimpia);
    $pathVista = $this->nombreVistaBlade('/' . ltrim((string) $any, '/'));
    if ($opcionMenu && trim((string) ($opcionMenu->de_dreccon ?? '')) !== '') {
        $pathVista = $this->nombreVistaBlade((string) $opcionMenu->de_dreccon);
    }
    if ($pathVista === 'mod_ti.helpdesk.index' || $pathVista === 'mod_ti.helpdesk.bandeja_externa') {
        return app(\App\Http\Controllers\mod_ti\helpdesk\IngresarServiciosController::class)->index($request);
    }

    // La ruta del menú en BD (de_dreccon) debe coincidir con la vista: /mod_ti/helpdesk/consulta
    if ($pathVista === 'mod_ti.helpdesk.consulta' || $pathVista === 'mod_ti.helpdesk.administracion') {
        return app(\App\Http\Controllers\mod_ti\helpdesk\ConsultasController::class)->index($request);
    }

    // Menú BD: /mod_ti/helpdesk/bandeja
    if ($pathVista === 'mod_ti.helpdesk.bandeja') {
        return app(\App\Http\Controllers\mod_ti\helpdesk\IngresarBandejaController::class)->index($request);
    }

    // Menú BD: /mod_ti/helpdesk/pantalla
    if ($pathVista === 'mod_ti.helpdesk.pantalla') {
        return app(\App\Http\Controllers\mod_ti\helpdesk\TicketsController::class)->index($request);
    }

    if ($pathVista === 'mod_ti.dispositivo_seg' || $pathVista === 'mod_ti.dispositivo_seg.index') {
        return app(\App\Http\Controllers\mod_ti\dispositivo_seg\DispositivoSegController::class)->index($request);
    }

    if ($pathVista === 'mod_ti.firma_email' || $pathVista === 'mod_ti.firma_email.index') {
        return app(\App\Http\Controllers\mod_ti\firma_email\FirmaEmailController::class)->index($request);
    }

    if ($pathVista === 'mod_ti.reporte_publicidad' || $pathVista === 'mod_ti.reporte_publicidad.index') {
        return app(\App\Http\Controllers\mod_ti\reporte_publicidad\ReportePublicidadController::class)->index($request);
    }

    if ($pathVista === 'mod_ti.reporte_publicidad_2026' || $pathVista === 'mod_ti.reporte_publicidad_2026.index') {
        return app(\App\Http\Controllers\mod_ti\reporte_publicidad_2026\ReportePublicidad2026Controller::class)->index($request);
    }
    if ($pathVista === 'mod_ti.dispositivo_seg.mapa') {
        return app(\App\Http\Controllers\mod_ti\dispositivo_seg\DispositivoSegController::class)->mapa($request);
    }
    if ($pathVista === 'mod_ti.dispositivo_seg.graficos') {
        return app(\App\Http\Controllers\mod_ti\dispositivo_seg\DispositivoSegController::class)->graficos($request);
    }

    if ($pathVista === 'mod_gp.omaped' || $pathVista === 'mod_gp.omaped.index') {
        return app(\App\Http\Controllers\mod_gp\omaped\OmapedController::class)->index($request);
    }

    if ($pathVista === 'mod_promesa' || $pathVista === 'mod_promesa.index') {
        return app(\App\Http\Controllers\mod_promesa\PromesaController::class)->index($request);
    }

    if ($this->esOpcionVerRequerimientoFinanzas($opcionMenu, $pathVista, (string) $any)) {
        return $this->abrirModulo(
            $request,
            \App\Http\Controllers\mod_a\sgl\VerRequerimientoFinanzasController::class,
            'mod_a.sgl.pago_proveedores.ver_requerimiento_finanzas',
            $opcionMenu
        );
    }

    if ($this->esOpcionVerRequerimientoLocador($opcionMenu, $pathVista, (string) $any)) {
        return $this->abrirModulo(
            $request,
            \App\Http\Controllers\mod_a\sgl\VerRequerimientoLocadorController::class,
            'mod_a.sgl.pago_proveedores.ver_requerimiento_locador',
            $opcionMenu
        );
    }

    if ($this->esOpcionVerRequerimientoLogistica($opcionMenu, $pathVista, (string) $any)) {
        return $this->abrirModulo(
            $request,
            \App\Http\Controllers\mod_a\sgl\VerRequerimientoLogisticaController::class,
            'mod_a.sgl.pago_proveedores.ver_requerimiento_logistica',
            $opcionMenu
        );
    }

    if ($this->esOpcionVerRequerimientoPresupuesto($opcionMenu, $pathVista, (string) $any)) {
        return $this->abrirModulo(
            $request,
            \App\Http\Controllers\mod_a\sgl\VerRequerimientoPresupuestoController::class,
            'mod_a.sgl.pago_proveedores.ver_requerimiento_presupuesto',
            $opcionMenu
        );
    }

    if ($this->esOpcionVerRequerimientoTesoreria($opcionMenu, $pathVista, (string) $any)) {
        return $this->abrirModulo(
            $request,
            \App\Http\Controllers\mod_a\sgl\VerRequerimientoTesoreriaController::class,
            'mod_a.sgl.pago_proveedores.ver_requerimiento_tesoreria',
            $opcionMenu
        );
    }

    if ($this->esOpcionPagoProveedoresIndex($opcionMenu, $pathVista, (string) $any)) {
        return $this->abrirModulo(
            $request,
            \App\Http\Controllers\mod_a\sgl\PagoProveedoresIndexController::class,
            'mod_a.sgl.pago_proveedores.index',
            $opcionMenu
        );
    }

    if ($this->esOpcionFichaSeleccionSeace($opcionMenu, $pathVista, (string) $any)) {
        return $this->abrirModulo(
            $request,
            \App\Http\Controllers\mod_a\sgl\FichaSeleccionSeaceController::class,
            'mod_a.sgl.index',
            $opcionMenu
        );
    }

    if ($pathVista === 'mod_a.sgl.pago_proveedores.buscar_requerimiento_pago' || $pathVista === 'mod_a.sgl.buscar_requerimiento_pago') {
        return app(\App\Http\Controllers\mod_a\sgl\BuscarRequerimientoPagoController::class)->index($request);
    }

    if ($pathVista === 'mod_a.sgl.pago_proveedores.reporte.reporte_gerencial') {
        return app(\App\Http\Controllers\mod_a\sgl\ReporteGerencialController::class)->index($request);
    }

    if ($pathVista === 'mod_a.sgl.pago_proveedores.v2_lista_reporte_requerimientos_general') {
        return app(\App\Http\Controllers\mod_a\sgl\V2ListaReporteRequerimientosGeneralController::class)->index($request);
    }

    if ($pathVista === 'mod_oac.acceso_informacion.acceso_info' || $pathVista === 'mod_oac.acceso_informacion.acceso_info.php') {
        return app(\App\Http\Controllers\mod_oac\AccesoInfoController::class)->index($request);
    }

    if ($pathVista === 'mod_gdu.band_consulta_gdu' || $pathVista === 'mod_gdu.band_consulta_gdu.php') {
        return app(\App\Http\Controllers\mod_gdu\BandConsultaGduController::class)->index();
    }

    if ($pathVista === 'mod_gdu.procedente_constancia' || $pathVista === 'mod_gdu.procedente_constancia.php') {
        return app(\App\Http\Controllers\mod_gdu\BandConsultaGduController::class)->procedenteConstancia($request);
    }

    if ($pathVista === 'mod_gdu.procedente_licencias' || $pathVista === 'mod_gdu.procedente_licencias.php') {
        return app(\App\Http\Controllers\mod_gdu\BandConsultaGduController::class)->procedenteLicencias($request);
    }

    if ($pathVista === 'mod_gene.mod_tram_inter.buscar-tramite') {
        return app(\App\Http\Controllers\mod_gene\mod_tram_inter\BuscarTramiteController::class)->index($request);
    }

    if ($pathVista === 'mod_gene.mod_tram_inter.correo-interno') {
        return app(\App\Http\Controllers\mod_gene\mod_tram_inter\CorreoInternoController::class)->index($request);
    }

    if ($pathVista === 'mod_gene.mod_tram_inter.ingresar-tramite') {
        return app(\App\Http\Controllers\mod_gene\mod_tram_inter\IngresarTramiteController::class)->index($request);
    }

    if ($pathVista === 'mod_gene.mod_tram_inter.reporte-cargo-interno') {
        return app(\App\Http\Controllers\mod_gene\mod_tram_inter\ReporteCargoInternoController::class)->index($request);
    }

    if ($pathVista === 'mod_gene.mod_tram_inter.v2_lista_reporte_area_cargo') {
        return app(\App\Http\Controllers\mod_gene\mod_tram_inter\V2ListaReporteAreaCargoController::class)->index($request);
    }

    // Menú BD: /mod_gene/mod_tram_inter/ver_secuencial_area (sin remap de nombre)
    if ($pathVista === 'mod_gene.mod_tram_inter.ver_secuencial_area') {
        return app(\App\Http\Controllers\mod_gene\mod_tram_inter\SecuencialTramiteController::class)->index($request);
    }

    if ($this->esOpcionCorreoExterno($opcionMenu, $pathVista)) {
        return $this->abrirModulo(
            $request,
            \App\Http\Controllers\mod_gm\mod_tram_exte\CorreoExternoController::class,
            $pathVista,
            $opcionMenu
        );
    }

    if ($this->esOpcionAdjuntar($opcionMenu, $pathVista)) {
        return $this->abrirModulo(
            $request,
            \App\Http\Controllers\mod_gm\mod_tram_exte\AdjuntarTramiteController::class,
            $pathVista,
            $opcionMenu
        );
    }

    if ($this->esOpcionArchivoExpediente($opcionMenu, $pathVista)) {
        return $this->abrirModulo(
            $request,
            \App\Http\Controllers\mod_gm\mod_tram_exte\ArchivoExpedienteController::class,
            $pathVista,
            $opcionMenu
        );
    }

    if ($this->esOpcionVerExpedi($opcionMenu, $pathVista)) {
        return $this->abrirModulo(
            $request,
            \App\Http\Controllers\mod_gm\mod_tram_exte\SegiVerExpediController::class,
            $pathVista,
            $opcionMenu
        );
    }

    if ($this->esOpcionListaExpediTodo($opcionMenu, $pathVista)) {
        return $this->abrirModulo(
            $request,
            \App\Http\Controllers\mod_gm\mod_tram_exte\ListaExpediTodoController::class,
            $pathVista,
            $opcionMenu
        );
    }

    if ($this->esOpcionListaReporArea($opcionMenu, $pathVista)) {
        return $this->abrirModulo(
            $request,
            \App\Http\Controllers\mod_gm\mod_tram_exte\ListaReporAreaController::class,
            $pathVista,
            $opcionMenu
        );
    }

    if ($this->esOpcionV2ListaReporteAreaCargoExterno($opcionMenu, $pathVista)) {
        return $this->abrirModulo(
            $request,
            \App\Http\Controllers\mod_gm\mod_tram_exte\V2ListaReporteAreaCargoExternoController::class,
            $pathVista,
            $opcionMenu
        );
    }

    if ($this->esOpcionReporteExpediente($opcionMenu, $pathVista)) {
        return $this->abrirModulo(
            $request,
            \App\Http\Controllers\mod_gm\mod_tram_exte\ReporteExpedienteController::class,
            $pathVista,
            $opcionMenu
        );
    }

    if ($this->esOpcionListaReporte($opcionMenu, $pathVista)) {
        return $this->abrirModulo(
            $request,
            \App\Http\Controllers\mod_gm\mod_tram_exte\ListaReporteController::class,
            $pathVista,
            $opcionMenu
        );
    }

    if ($this->esOpcionRedirigir($opcionMenu, $pathVista)) {
        return $this->abrirModulo(
            $request,
            \App\Http\Controllers\mod_gm\mod_tram_exte\RedirigirExpedienteController::class,
            $pathVista,
            $opcionMenu
        );
    }

    if ($pathVista === 'mod_gene.mod_tram_inter.reporte-tramite') {
        return app(\App\Http\Controllers\mod_gene\mod_tram_inter\ReporteTramiteController::class)->index($request);
    }

    // Menú BD: /mod_reportes/reporte_tramites (sin Route::get; solo catch-all)
    if ($pathVista === 'mod_reportes.reporte_tramites') {
        return app(\App\Http\Controllers\mod_reportes\ReporteTramitesController::class)->index($request);
    }

    if ($pathVista === 'mod_seguridad.seguridad-perfiles') {
        return app(\App\Http\Controllers\mod_seguridad\PerfilController::class)->index($request);
    }

    if ($pathVista === 'mod_seguridad.seguridad-cargos') {
        return app(\App\Http\Controllers\mod_seguridad\CargoController::class)->index($request);
    }

    if ($pathVista === 'mod_seguridad.seguridad-opciones') {
        return app(\App\Http\Controllers\mod_seguridad\OpcionController::class)->index($request);
    }

    if ($pathVista === 'mod_seguridad.seguridad-usuario') {
        return app(\App\Http\Controllers\mod_seguridad\UsuarioController::class)->index($request);
    }

    if ($pathVista === 'mod_seguridad.seguridad-accesos') {
        return app(\App\Http\Controllers\mod_seguridad\AccesoController::class)->index($request);
    }

    if ($pathVista === 'mod_seguridad.seguridad-perfiles-opciones') {
        return app(\App\Http\Controllers\mod_seguridad\PerfilOpcionController::class)->index($request);
    }

    if ($pathVista === 'mod_seguridad.cambiar-contrasena' || $pathVista === 'mod_seguridad.cambiar-contraseña') {
        return app(\App\Http\Controllers\mod_seguridad\PasswordController::class)->index($request);
    }

    if ($this->esOpcionIngresoExpedientes($opcionMenu)) {
        return $this->abrirModulo(
            $request,
            \App\Http\Controllers\mod_gm\mod_tram_exte\IngresoExpedienteController::class,
            $pathVista,
            $opcionMenu
        );
    }

    if ($this->esOpcionNotificarPlataforma($opcionMenu)) {
        return $this->abrirModulo(
            $request,
            \App\Http\Controllers\mod_gm\mod_tram_exte\NotificarPlataformaController::class,
            $pathVista,
            $opcionMenu
        );
    }

    if ($this->existeVistaBlade($pathVista)) {
        return view($pathVista);
    }

    abort(404, "El archivo de vista no existe.");
}

    private function abrirModulo(Request $request, string $controller, string $pathVista, ?object $opcionMenu)
    {
        if ($pathVista === '' || ! $this->existeVistaBlade($pathVista)) {
            abort(404, 'El archivo de vista no existe.');
        }

        return app($controller)->index($request, $pathVista, $opcionMenu);
    }

    private function etiquetaOpcion(?object $opcion): string
    {
        if ($opcion === null) {
            return '';
        }

        $label = mb_strtolower(preg_replace('/\s+/', ' ', trim((string) ($opcion->dc_rle ?? ''))) ?? '');

        return str_replace(['á', 'é', 'í', 'ó', 'ú', 'ñ'], ['a', 'e', 'i', 'o', 'u', 'n'], $label);
    }

    /**
     * Identifica las opciones de mesa de partes por el nombre del menú (a_rles.dc_rle), no por el archivo.
     */
    private function esOpcionIngresoExpedientes(?object $opcion): bool
    {
        $label = $this->etiquetaOpcion($opcion);

        return in_array($label, ['ingresar expedientes', 'ingresar prescripcion'], true);
    }

    /**
     * Identifica la opción de menú "Notificar Plataforma" por el nombre, no por el archivo.
     */
    private function esOpcionNotificarPlataforma(?object $opcion): bool
    {
        $label = $this->etiquetaOpcion($opcion);

        return $label === 'notificar plataforma' || str_contains($label, 'notificar plataforma');
    }

    private function esOpcionCorreoExterno(?object $opcion, string $pathVista): bool
    {
        if ($pathVista === 'mod_gm.mod_tram_exte.segi_verexpedi') {
            return false;
        }

        $label = $this->etiquetaOpcion($opcion);
        if ($label === 'correo externo' || str_contains($label, 'correo externo')) {
            return true;
        }

        return $pathVista === 'mod_gm.mod_tram_exte.segi_expedi';
    }

    private function esOpcionVerExpedi(?object $opcion, string $pathVista): bool
    {
        $label = $this->etiquetaOpcion($opcion);
        if (
            $label === 'segi verexpedi'
            || str_contains($label, 'segi verexpedi')
            || str_contains($label, 'ver expediente')
            || str_contains($label, 'seguimiento del expediente')
        ) {
            return true;
        }

        return $pathVista === 'mod_gm.mod_tram_exte.segi_verexpedi';
    }

    private function esOpcionAdjuntar(?object $opcion, string $pathVista): bool
    {
        $label = $this->etiquetaOpcion($opcion);
        if ($label === 'adjuntar' || str_contains($label, 'adjuntar')) {
            return true;
        }

        return $pathVista === 'mod_gm.mod_tram_exte.segi_adjuntar';
    }

    private function esOpcionArchivoExpediente(?object $opcion, string $pathVista): bool
    {
        $label = $this->etiquetaOpcion($opcion);
        if ($label === 'archivo' || str_contains($label, 'bandeja de archivo') || str_contains($label, 'archivo de expediente')) {
            return true;
        }

        return $pathVista === 'mod_gm.mod_tram_exte.segi_archivo';
    }

    private function esOpcionReporteExpediente(?object $opcion, string $pathVista): bool
    {
        if (
            $pathVista === 'mod_gm.mod_tram_exte.lista_repor_area'
            || $pathVista === 'mod_gm.mod_tram_exte.lista_expedi_todo'
            || $pathVista === 'mod_gm.mod_tram_exte.v2_lista_reporte_area_cargo'
        ) {
            return false;
        }

        $label = $this->etiquetaOpcion($opcion);
        if (
            $label === 'reporte expediente'
            || str_contains($label, 'reporte de expediente')
            || str_contains($label, 'reporte expediente')
        ) {
            return true;
        }

        return $pathVista === 'mod_gm.mod_tram_exte.reporte-expediente';
    }

    private function esOpcionPagoProveedoresIndex(?object $opcion, string $pathVista, string $any = ''): bool
    {
        $anyNorm = strtolower(trim(str_replace('\\', '/', $any), '/'));
        $anyNorm = preg_replace('/\.blade\.php$/i', '', $anyNorm) ?? $anyNorm;
        $anyNorm = preg_replace('/\.php$/i', '', $anyNorm) ?? $anyNorm;

        if (in_array($anyNorm, ['mod_a/sgl/pago_proveedores/index', 'mod_a/sgl/pago_proveedores'], true)) {
            return ! str_contains($anyNorm, 'buscar')
                && ! str_contains($anyNorm, 'reporte')
                && ! str_contains($anyNorm, 'v2_lista');
        }

        if ($pathVista === 'mod_a.sgl.pago_proveedores.index') {
            return true;
        }

        $label = $this->etiquetaOpcion($opcion);
        if ($label === '') {
            return false;
        }

        if (
            str_contains($label, 'buscar')
            || str_contains($label, 'reporte')
            || str_contains($label, 'ficha')
            || str_contains($label, 'finanza')
            || str_contains($label, 'locador')
            || str_contains($label, 'logistic')
            || str_contains($label, 'presupuest')
            || str_contains($label, 'tesorer')
            || str_contains($label, 'ver requerimiento')
        ) {
            return false;
        }

        return str_contains($label, 'pago') && str_contains($label, 'proveedor');
    }

    private function esOpcionVerRequerimientoFinanzas(?object $opcion, string $pathVista, string $any = ''): bool
    {
        $anyNorm = strtolower(trim(str_replace('\\', '/', $any), '/'));
        $anyNorm = preg_replace('/\.blade\.php$/i', '', $anyNorm) ?? $anyNorm;
        $anyNorm = preg_replace('/\.php$/i', '', $anyNorm) ?? $anyNorm;

        if ($anyNorm === 'mod_a/sgl/pago_proveedores/ver_requerimiento_finanzas') {
            return true;
        }

        if ($pathVista === 'mod_a.sgl.pago_proveedores.ver_requerimiento_finanzas') {
            return true;
        }

        $label = $this->etiquetaOpcion($opcion);

        return $label !== '' && str_contains($label, 'finanza');
    }

    private function esOpcionVerRequerimientoLocador(?object $opcion, string $pathVista, string $any = ''): bool
    {
        $anyNorm = strtolower(trim(str_replace('\\', '/', $any), '/'));
        $anyNorm = preg_replace('/\.blade\.php$/i', '', $anyNorm) ?? $anyNorm;
        $anyNorm = preg_replace('/\.php$/i', '', $anyNorm) ?? $anyNorm;

        if ($anyNorm === 'mod_a/sgl/pago_proveedores/ver_requerimiento_locador') {
            return true;
        }

        if ($pathVista === 'mod_a.sgl.pago_proveedores.ver_requerimiento_locador') {
            return true;
        }

        $label = $this->etiquetaOpcion($opcion);

        return $label !== '' && str_contains($label, 'locador');
    }

    private function esOpcionVerRequerimientoLogistica(?object $opcion, string $pathVista, string $any = ''): bool
    {
        $anyNorm = strtolower(trim(str_replace('\\', '/', $any), '/'));
        $anyNorm = preg_replace('/\.blade\.php$/i', '', $anyNorm) ?? $anyNorm;
        $anyNorm = preg_replace('/\.php$/i', '', $anyNorm) ?? $anyNorm;

        if ($anyNorm === 'mod_a/sgl/pago_proveedores/ver_requerimiento_logistica') {
            return true;
        }

        if ($pathVista === 'mod_a.sgl.pago_proveedores.ver_requerimiento_logistica') {
            return true;
        }

        $label = $this->etiquetaOpcion($opcion);

        return $label !== '' && str_contains($label, 'logistic');
    }

    private function esOpcionVerRequerimientoPresupuesto(?object $opcion, string $pathVista, string $any = ''): bool
    {
        $anyNorm = strtolower(trim(str_replace('\\', '/', $any), '/'));
        $anyNorm = preg_replace('/\.blade\.php$/i', '', $anyNorm) ?? $anyNorm;
        $anyNorm = preg_replace('/\.php$/i', '', $anyNorm) ?? $anyNorm;

        if ($anyNorm === 'mod_a/sgl/pago_proveedores/ver_requerimiento_presupuesto') {
            return true;
        }

        if ($pathVista === 'mod_a.sgl.pago_proveedores.ver_requerimiento_presupuesto') {
            return true;
        }

        $label = $this->etiquetaOpcion($opcion);

        return $label !== ''
            && str_contains($label, 'presupuest')
            && (str_contains($label, 'pago') || str_contains($label, 'proveedor') || str_contains($label, 'requerimiento'));
    }

    private function esOpcionVerRequerimientoTesoreria(?object $opcion, string $pathVista, string $any = ''): bool
    {
        $anyNorm = strtolower(trim(str_replace('\\', '/', $any), '/'));
        $anyNorm = preg_replace('/\.blade\.php$/i', '', $anyNorm) ?? $anyNorm;
        $anyNorm = preg_replace('/\.php$/i', '', $anyNorm) ?? $anyNorm;

        if ($anyNorm === 'mod_a/sgl/pago_proveedores/ver_requerimiento_tesoreria') {
            return true;
        }

        if ($pathVista === 'mod_a.sgl.pago_proveedores.ver_requerimiento_tesoreria') {
            return true;
        }

        $label = $this->etiquetaOpcion($opcion);

        return $label !== '' && str_contains($label, 'tesorer');
    }

    private function esOpcionFichaSeleccionSeace(?object $opcion, string $pathVista, string $any = ''): bool
    {
        $anyNorm = strtolower(trim(str_replace('\\', '/', $any), '/'));
        $anyNorm = preg_replace('/\.blade\.php$/i', '', $anyNorm) ?? $anyNorm;
        $anyNorm = preg_replace('/\.php$/i', '', $anyNorm) ?? $anyNorm;

        if ($anyNorm === 'mod_a/sgl/index' || $anyNorm === 'mod_a/sgl') {
            return true;
        }

        if ($pathVista === 'mod_a.sgl.index') {
            return true;
        }

        $label = $this->etiquetaOpcion($opcion);
        if ($label === '') {
            return false;
        }

        return str_contains($label, 'ficha')
            && (str_contains($label, 'seleccion') || str_contains($label, 'seace'));
    }

    private function esOpcionV2ListaReporteAreaCargoExterno(?object $opcion, string $pathVista): bool
    {
        if ($pathVista === 'mod_gene.mod_tram_inter.v2_lista_reporte_area_cargo') {
            return false;
        }

        $label = $this->etiquetaOpcion($opcion);
        if (
            $label !== ''
            && (
                str_contains($label, 'monitoreo en tiempo real')
                || str_contains($label, 'gestion por cargo')
                || str_contains($label, 'v2 lista reporte area cargo')
            )
            && (
                $pathVista === ''
                || str_contains($pathVista, 'mod_gm.mod_tram_exte')
                || str_contains($pathVista, 'v2_lista_reporte_area_cargo')
            )
        ) {
            return $pathVista !== 'mod_gene.mod_tram_inter.v2_lista_reporte_area_cargo';
        }

        return $pathVista === 'mod_gm.mod_tram_exte.v2_lista_reporte_area_cargo';
    }

    private function esOpcionListaReporArea(?object $opcion, string $pathVista): bool
    {
        $label = $this->etiquetaOpcion($opcion);
        if (
            $label === 'lista repor area'
            || str_contains($label, 'lista repor area')
        ) {
            return true;
        }

        return $pathVista === 'mod_gm.mod_tram_exte.lista_repor_area';
    }

    private function esOpcionListaExpediTodo(?object $opcion, string $pathVista): bool
    {
        $label = $this->etiquetaOpcion($opcion);
        if (
            $label === 'lista expedi todo'
            || str_contains($label, 'lista expedi todo')
            || str_contains($label, 'todos los expedientes')
        ) {
            return true;
        }

        return $pathVista === 'mod_gm.mod_tram_exte.lista_expedi_todo';
    }

    private function esOpcionListaReporte(?object $opcion, string $pathVista): bool
    {
        if (
            $pathVista === 'mod_gm.mod_tram_exte.lista_expedi_todo'
            || $pathVista === 'mod_gm.mod_tram_exte.v2_lista_reporte_area_cargo'
        ) {
            return false;
        }

        $label = $this->etiquetaOpcion($opcion);
        if (
            $label === 'lista reporte'
            || str_contains($label, 'lista reporte')
            || str_contains($label, 'expedientes ingresados')
        ) {
            return true;
        }

        return $pathVista === 'mod_gm.mod_tram_exte.lista_reporte';
    }

    private function esOpcionRedirigir(?object $opcion, string $pathVista): bool
    {
        $label = $this->etiquetaOpcion($opcion);
        if ($label === 'redirigir' || str_contains($label, 'redirigir')) {
            return true;
        }

        return $pathVista === 'mod_gm.mod_tram_exte.redirigir';
    }

}
