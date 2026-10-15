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
use App\Services\Core\ModuleDispatcher;
use App\Services\Core\ModuleRouteNormalizer;
use App\Support\SessionKeys;

class AuthController extends Controller
{
    /**
     * @var SecurityCoreInterface
     */
    protected SecurityCoreInterface $securityCore;

    /**
     * @var ModuleRouteNormalizer
     */
    protected ModuleRouteNormalizer $normalizer;

    /**
     * @var ModuleDispatcher
     */
    protected ModuleDispatcher $dispatcher;

    /** Active company/cargo context service. */
    protected \App\Contracts\Services\ContextoUsuarioAutenticadoServiceInterface $contextoService;

    /**
     * AuthController constructor.
     *
     * @param SecurityCoreInterface $securityCore
     * @param ModuleRouteNormalizer|null $normalizer
     * @param ModuleDispatcher|null $dispatcher
     * @param \App\Contracts\Services\ContextoUsuarioAutenticadoServiceInterface|null $contextoService
     */
    public function __construct(
        SecurityCoreInterface $securityCore,
        ?ModuleRouteNormalizer $normalizer = null,
        ?ModuleDispatcher $dispatcher = null,
        ?\App\Contracts\Services\ContextoUsuarioAutenticadoServiceInterface $contextoService = null
    ) {
        $this->securityCore = $securityCore;
        $this->normalizer = $normalizer ?? new ModuleRouteNormalizer();
        $this->dispatcher = $dispatcher ?? new ModuleDispatcher();
        $this->contextoService = $contextoService ?? app(\App\Contracts\Services\ContextoUsuarioAutenticadoServiceInterface::class);
    }

    public const CLAVES_CONTEXTO_OPERATIVO = [
        SessionKeys::COMPANIA_ID,
        SessionKeys::COMPANIA_NOMBRE,
        SessionKeys::CARGO_ID,
        SessionKeys::CARGO_NOMBRE,
        SessionKeys::AREA_ID,
        SessionKeys::AREA_NOMBRE,
        SessionKeys::LOGIN_ID,
        SessionKeys::USUARIO_ID,
        SessionKeys::LOGIN_NOMBRE,
        SessionKeys::PERFIL_ID,
        'sigtram_compania_id',
        'sigtram_compania_nombre',
    ];

    /**
     * Limpia completamente el contexto operativo anterior en la sesión.
     */
    public function limpiarContextoOperativo(Request $request): void
    {
        $this->contextoService->limpiarContexto();
        $request->session()->forget(self::CLAVES_CONTEXTO_OPERATIVO);
    }

    public function seleccionarCompania(Request $request)
    {
        $user = Auth::user();
        if (!$user instanceof User) {
            return redirect()->route('login');
        }

        $idLogin = (int) $user->cdgo_lgin;
        $companias = $this->securityCore->obtenerCompaniasUsuario($idLogin);

        if ($companias->isEmpty()) {
            Auth::logout();
            $request->session()->invalidate();
            throw ValidationException::withMessages([
                'usuario' => 'No tiene compañías autorizadas activas para ingresar al sistema.',
            ]);
        }

        if ($companias->count() === 1) {
            $compania = $companias->first();
            $companiaId = (int) $compania->cdgo_cmpnia;
            $request->session()->put(SessionKeys::COMPANIA_ID, $companiaId);
            $request->session()->put(SessionKeys::COMPANIA_NOMBRE, (string) $compania->de_cmpnia);
            $request->session()->forget('sigtram_compania_id');

            return redirect()->route('seleccionar.cargo');
        }

        return view('seleccionar-compania', [
            'companias' => $companias,
            'usuario' => $user->nombreMostrar(),
        ]);
    }

    public function guardarCompania(Request $request)
    {
        $user = Auth::user();
        if (!$user instanceof User) {
            return redirect()->route('login');
        }

        $data = $request->validate([
            'compania_id' => ['required', 'integer'],
        ]);

        $idLogin = (int) $user->cdgo_lgin;
        $companiaId = (int) $data['compania_id'];

        $companias = $this->securityCore->obtenerCompaniasUsuario($idLogin);
        $compania = $companias->firstWhere('cdgo_cmpnia', $companiaId);

        if ($compania === null) {
            throw ValidationException::withMessages([
                'compania_id' => 'La compañía seleccionada no está autorizada para este usuario.',
            ]);
        }

        $request->session()->put(SessionKeys::COMPANIA_ID, $companiaId);
        $request->session()->put(SessionKeys::COMPANIA_NOMBRE, (string) $compania->de_cmpnia);
        $request->session()->forget('sigtram_compania_id');

        $request->session()->forget([
            'sigtram_cargo_id',
            'sigtram_cargo_nombre',
            'sigtram_area_nombre',
            'sigtram_area_id',
            SessionKeys::CARGO_ID,
            SessionKeys::AREA_ID,
        ]);

        return redirect()->route('seleccionar.cargo');
    }

    public function seleccionarCargo(Request $request)
    {
        $user = Auth::user();
        if (!$user instanceof User) {
            return redirect()->route('login');
        }

        $idLogin = (int) $user->cdgo_lgin;
        $companias = $this->securityCore->obtenerCompaniasUsuario($idLogin);

        if ($companias->isEmpty()) {
            Auth::logout();
            $request->session()->invalidate();
            throw ValidationException::withMessages([
                'usuario' => 'No tiene compañías autorizadas activas en el sistema.',
            ]);
        }

        // 1. Obtener compañía activa en sesión y verificar que aparezca entre las autorizadas del login
        $companiaId = (int) $request->session()->get(SessionKeys::COMPANIA_ID, 0);

        if ($companiaId > 0) {
            $compania = $companias->firstWhere('cdgo_cmpnia', $companiaId);
            if ($compania === null) {
                // Compañía no autorizada para el login actual -> limpiar y redirigir a seleccionar.compania
                $this->limpiarContextoOperativo($request);
                return redirect()->route('seleccionar.compania');
            }
        } else {
            // Sin compañía en sesión: autoseleccionar si hay 1 sola, o redirigir al selector si hay varias
            if ($companias->count() === 1) {
                $compania = $companias->first();
                $companiaId = (int) $compania->cdgo_cmpnia;
            } else {
                return redirect()->route('seleccionar.compania');
            }
        }

        $request->session()->put(SessionKeys::COMPANIA_ID, $companiaId);
        $request->session()->put(SessionKeys::COMPANIA_NOMBRE, (string) $compania->de_cmpnia);
        $request->session()->forget('sigtram_compania_id');

        // 2. Obtener cargos autorizados para la compañía activa
        $cargos = $this->securityCore->obtenerCargosUsuario($idLogin, $companiaId);

        if ($cargos->isEmpty()) {
            throw ValidationException::withMessages([
                'usuario' => 'No tiene cargos asignados en la compañía activa para ingresar al sistema.',
            ]);
        }

        if ($cargos->count() === 1) {
            $cargo = $cargos->first();
            $cargoId = (int) $cargo->cdgo_crgo;
            $areaId = (int) ($cargo->cdgo_area ?? 0);
            $cargoCmpnia = (int) ($cargo->cdgo_cmpnia ?? 0);

            if ($cargoCmpnia !== $companiaId) {
                throw ValidationException::withMessages([
                    'usuario' => 'El cargo asignado no pertenece a la compañía seleccionada.',
                ]);
            }

            $this->contextoService->establecerContexto($companiaId, $cargoId);

            return redirect()->route('inicio');
        }

        return view('seleccionar-cargo', [
            'cargos' => $cargos,
            'usuario' => $user->nombreMostrar(),
            'companiaNombre' => (string) $request->session()->get(SessionKeys::COMPANIA_NOMBRE, (string) $compania->de_cmpnia),
        ]);
    }

    public function guardarCargo(Request $request)
    {
        $user = Auth::user();
        if (!$user instanceof User) {
            return redirect()->route('login');
        }

        $idLogin = (int) $user->cdgo_lgin;
        $companiaId = (int) $request->session()->get(SessionKeys::COMPANIA_ID, 0);

        // Sin compañía activa y autorizada debe fallar cerrado o redirigir a seleccionar.compania
        if ($companiaId <= 0) {
            $this->limpiarContextoOperativo($request);
            return redirect()->route('seleccionar.compania');
        }

        $companias = $this->securityCore->obtenerCompaniasUsuario($idLogin);
        $compania = $companias->firstWhere('cdgo_cmpnia', $companiaId);
        if ($compania === null) {
            $this->limpiarContextoOperativo($request);
            return redirect()->route('seleccionar.compania');
        }

        $data = $request->validate([
            'cargo_id' => ['required', 'integer'],
        ]);

        $cargoId = (int) $data['cargo_id'];

        $cargos = $this->securityCore->obtenerCargosUsuario($idLogin, $companiaId);
        $cargo = $cargos->firstWhere('cdgo_crgo', $cargoId);

        if ($cargo === null) {
            throw ValidationException::withMessages([
                'cargo_id' => 'El cargo seleccionado no es válido para este usuario en la compañía activa.',
            ]);
        }

        $cargoCmpnia = (int) ($cargo->cdgo_cmpnia ?? 0);
        if ($cargoCmpnia !== $companiaId) {
            throw ValidationException::withMessages([
                'cargo_id' => 'El cargo seleccionado no pertenece a la compañía activa.',
            ]);
        }

        $this->contextoService->establecerContexto($companiaId, $cargoId);

        return redirect()->route('inicio');
    }

    public function inicio(Request $request)
    {
        $user = Auth::user();
        if (!$user instanceof User) {
            return redirect()->route('login');
        }

        $idLogin = (int) $user->cdgo_lgin;
        $companias = $this->securityCore->obtenerCompaniasUsuario($idLogin);
        if ($companias->isEmpty()) {
            $this->limpiarContextoOperativo($request);
            return redirect()->route('login');
        }

        $companiaId = (int) $request->session()->get(SessionKeys::COMPANIA_ID, 0);
        if ($companiaId <= 0 || !$companias->contains('cdgo_cmpnia', $companiaId)) {
            $this->limpiarContextoOperativo($request);
            return redirect()->route('seleccionar.compania');
        }

        $cargos = $this->securityCore->obtenerCargosUsuario($idLogin, $companiaId);
        $cargoId = (int) $request->session()->get(SessionKeys::CARGO_ID, 0);
        $cargoValido = $cargos->firstWhere('cdgo_crgo', $cargoId);

        if (!$cargoValido) {
            $this->limpiarContextoOperativo($request);
            $request->session()->put(SessionKeys::COMPANIA_ID, $companiaId);
            return redirect()->route('seleccionar.cargo');
        }

        $menuItems = $this->securityCore->obtenerMenuUsuario($idLogin, $cargoId);

        return view('inicio', [
            'menuItems' => $menuItems,
            'area' => (string) $request->session()->get(SessionKeys::AREA_NOMBRE, (string) ($cargoValido->de_area ?? '-')),
            'cargo' => (string) $request->session()->get(SessionKeys::CARGO_NOMBRE, (string) ($cargoValido->de_crgo ?? '-')),
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
        $this->limpiarContextoOperativo($request);

        return redirect()->route('seleccionar.compania');

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





    public function cargarModulo(Request $request, $any)
    {
        // 1. Descartar archivos estáticos (.png, .jpg, .css, .js, etc.)
        if (preg_match('/\.(jpg|jpeg|png|gif|svg|ico|css|js|map|woff|woff2|ttf|eot)$/i', (string) $any)) {
            abort(404, 'Archivo no encontrado.');
        }

        // 2. Obtener usuario autenticado
        $user = Auth::user();
        if (!$user) {
            return redirect()->route('login');
        }

        // 3. Obtener el cargo activo
        $cargoId = (int) $request->session()->get(SessionKeys::CARGO_ID, 0);

        // 4. Si el cargo es obligatorio y cdgo_crgo es cero o inválido, responder de forma controlada con 403
        if ($cargoId <= 0) {
            abort(403, 'Debe seleccionar un cargo activo.');
        }

        // 5. Normalizar la ruta solicitada con el normalizador único
        $rutaCanonica = $this->normalizer->normalize((string) $any);
        if ($rutaCanonica === '') {
            abort(404, 'Ruta no válida.');
        }

        // 6. Comprobar explícitamente que la ruta URL no esté vacía
        $rutaUrl = $this->normalizer->toUrlPath($rutaCanonica);
        if ($rutaUrl === '') {
            abort(404, 'Ruta no válida.');
        }

        // 7. Ejecutar la autorización con la ruta URL correcta antes de consultar opciones o despachar
        $tienePermiso = $this->securityCore->tienePermiso((int) $user->cdgo_lgin, $rutaUrl, $cargoId);
        if (!$tienePermiso) {
            abort(403, 'No tienes permiso para acceder a este módulo.');
        }

        // 8. Obtener la opción de menú autorizada
        $opcionMenu = $this->securityCore->obtenerOpcionPorRuta($rutaUrl);

        // 9. Construir la clave efectiva desde de_dreccon, si corresponde
        $claveEfectiva = $rutaCanonica;
        if ($opcionMenu && trim((string) ($opcionMenu->de_dreccon ?? '')) !== '') {
            $claveDesdeOpcion = $this->normalizer->normalize((string) $opcionMenu->de_dreccon);
            if ($claveDesdeOpcion !== '') {
                $claveEfectiva = $claveDesdeOpcion;
            }
        }

        // 10. Despacho determinista centralizado
        if ($this->dispatcher->has($claveEfectiva)) {
            return $this->dispatcher->dispatch($claveEfectiva, $request, $opcionMenu);
        }

        if ($this->dispatcher->has($rutaCanonica)) {
            return $this->dispatcher->dispatch($rutaCanonica, $request, $opcionMenu);
        }

        // 11. Fallback controlado: renderizado directo si existe vista Blade autorizada
        if ($this->dispatcher->existeVistaBlade($claveEfectiva)) {
            return view($claveEfectiva);
        }

        if ($this->dispatcher->existeVistaBlade($rutaCanonica)) {
            return view($rutaCanonica);
        }

        // 12. Sin handler ni vista: 404 seguro (no redirigir a /inicio)
        abort(404, 'El archivo de vista no existe.');
    }
}
