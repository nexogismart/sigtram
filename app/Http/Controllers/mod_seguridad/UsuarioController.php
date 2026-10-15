<?php

namespace App\Http\Controllers\mod_seguridad;

use App\Http\Controllers\Controller;
// use App\Support\SigtramPassword; // migración SHA1→bcrypt (desactivada por ahora)
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class UsuarioController extends Controller
{
    private function getSchema(): string
    {
        return rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';
    }

    private function sanitizeText($value): string
    {
        if (!is_string($value)) {
            return '';
        }
        return preg_replace('/\s+/', ' ', trim($value));
    }

    private function estadoAcceso($valor): string
    {
        if (is_array($valor)) {
            $valor = end($valor);
        }
        $valor = trim((string) $valor);

        return $valor === '0' ? '0' : '1';
    }

    private function sincronizarCumpleanos(string $schema, $cdgoUsrios, $cdgoArea, bool $activo): void
    {
        if (! $cdgoUsrios) {
            return;
        }

        $tabla = $schema . 'p_usrios_cmplños';

        if (! $activo || $cdgoArea === null || $cdgoArea === '') {
            DB::table($tabla)->where('cdgo_usrios', $cdgoUsrios)->update(['in_esta' => '0']);
            return;
        }

        $filas = DB::table($tabla)
            ->where('cdgo_usrios', $cdgoUsrios)
            ->orderBy('cdgo_usrios_cmplños')
            ->get();

        $mismaArea = $filas->first(fn ($f) => (string) $f->cdgo_area === (string) $cdgoArea);

        if ($mismaArea) {
            DB::table($tabla)
                ->where('cdgo_usrios_cmplños', $mismaArea->cdgo_usrios_cmplños)
                ->update(['in_esta' => '1']);
            return;
        }

        $otra = $filas->first();

        if ($otra) {
            DB::table($tabla)
                ->where('cdgo_usrios_cmplños', $otra->cdgo_usrios_cmplños)
                ->update([
                    'cdgo_area' => $cdgoArea,
                    'in_esta' => '1',
                ]);
            return;
        }

        DB::table($tabla)->insert([
            'cdgo_usrios' => $cdgoUsrios,
            'cdgo_area' => $cdgoArea,
            'in_esta' => '1',
        ]);
    }

    public function index(Request $request)
    {
        $schema = $this->getSchema();
        
        $query = DB::table($schema . 'p_lgin as l')
            ->join($schema . 'p_usrios as u', 'l.cdgo_usrios', '=', 'u.cdgo_usrios')
            ->leftJoin($schema . 'h_usrios_ntral as n', 'u.cdgo_usrios', '=', 'n.cdgo_usrios')
            ->join($schema . 'p_prfles as p', 'l.cdgo_prfle', '=', 'p.cdgo_prfle')
            ->select(
                'l.cdgo_lgin',
                'l.de_lgin',
                'l.cdgo_prfle',
                'l.in_esta as lgin_esta',
                'l.cdgo_usrios',
                'u.no_crto',
                'u.nu_docu',
                'u.de_mail',
                'u.nu_tele',
                'u.de_dire',
                'p.nc_prfle',
                'n.ap_pate',
                'n.ap_mate',
                'n.no_usrio'
            );

        if ($request->filled('criterio')) {
            $criterio = trim($request->input('criterio'));
            $query->where(function($q) use ($criterio) {
                $q->where('u.no_crto', 'like', '%' . strtoupper($this->sanitizeText($criterio)) . '%')
                  ->orWhere('l.de_lgin', 'like', '%' . strtolower(preg_replace('/\s+/', '', $criterio)) . '%')
                  ->orWhere('u.nu_docu', 'like', '%' . $criterio . '%');
            });
        }

        $usuarios = $query->orderBy('u.no_crto', 'asc')->paginate(10);

        $usuarios->getCollection()->transform(function ($item) {
            if (empty($item->ap_pate) && empty($item->no_usrio) && !empty($item->no_crto)) {
                $partes = array_values(array_filter(explode(' ', trim((string) $item->no_crto))));
                $total = count($partes);
                if ($total >= 3) {
                    $item->ap_mate = array_pop($partes);
                    $item->ap_pate = array_pop($partes);
                    $item->no_usrio = implode(' ', $partes);
                } elseif ($total === 2) {
                    $item->ap_pate = $partes[1];
                    $item->no_usrio = $partes[0];
                    $item->ap_mate = '';
                } else {
                    $item->no_usrio = $partes[0] ?? $item->no_crto;
                    $item->ap_pate = '';
                    $item->ap_mate = '';
                }
            }
            return $item;
        });
        
        $perfiles = DB::table($schema . 'p_prfles')->where('in_esta', '1')->orderBy('nc_prfle', 'asc')->get();
        $companias = DB::table($schema . 'a_cmpnias')->orderBy('de_cmpnia', 'asc')->get();
        $areas = DB::table($schema . 'a_areas')->where('in_esta', '1')->orderBy('de_area', 'asc')->get();

        if ($request->ajax()) {
            return response()->json([
                'usuarios' => $usuarios,
                'perfiles' => $perfiles,
                'companias' => $companias,
                'areas' => $areas
            ]);
        }

        return view('mod_seguridad.seguridad-usuario', compact('usuarios', 'perfiles', 'companias', 'areas'));
    }

    public function buscarDni($dni)
    {
        $schema = $this->getSchema();
        
        if (!preg_match('/^\d{8}$/', $dni)) {
            return response()->json([
                'success' => false,
                'message' => 'El formato del DNI debe ser exactamente de 8 dígitos numéricos.'
            ], 422);
        }

        $user = DB::table($schema . 'p_usrios as u')
            ->leftJoin($schema . 'h_usrios_ntral as n', 'u.cdgo_usrios', '=', 'n.cdgo_usrios')
            ->select('u.*', 'n.ap_pate', 'n.ap_mate', 'n.no_usrio', 'n.cdgo_estdo_cvil', 'n.in_sexo')
            ->where('u.nu_docu', $dni)
            ->first();

        if ($user) {
            return response()->json([
                'success' => true,
                'user' => $user
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'DNI no encontrado'
        ]);
    }

    public function store(Request $request)
    {
        $schema = $this->getSchema();
        
        $esEdicion = $request->filled('cdgo_lgin');
        $tabla = fn (string $t) => DB::getDefaultConnection() . '.' . $schema . $t;
        $claveNueva = trim((string) $request->input('de_pass', ''));
        $claveConf = trim((string) $request->input('de_pass_confirmation', ''));
        $cambiarClave = $claveNueva !== '' && $claveConf !== '';

        if ($esEdicion && ! $cambiarClave) {
            $request->merge([
                'de_pass' => null,
                'de_pass_confirmation' => null,
            ]);
        }

        $in_esta = $this->estadoAcceso($request->input('in_esta', '1'));
        $request->merge(['in_esta' => $in_esta]);

        $request->validate([
            'cdgo_lgin' => ['nullable', 'integer', 'exists:' . $tabla('p_lgin') . ',cdgo_lgin'],
            'nu_docu' => ['required', 'string', 'digits:8', 'regex:/^\d{8}$/'],
            'ap_pate' => ['required', 'string', 'max:100', 'regex:/^[A-Za-zÁÉÍÓÚáéíóúÑñÜü\' -]+$/'],
            'ap_mate' => ['required', 'string', 'max:100', 'regex:/^[A-Za-zÁÉÍÓÚáéíóúÑñÜü\' -]+$/'],
            'no_usrio' => ['required', 'string', 'max:100', 'regex:/^[A-Za-zÁÉÍÓÚáéíóúÑñÜü\' -]+$/'],
            'de_dire' => ['nullable', 'string', 'max:250'],
            'nu_tele' => ['nullable', 'string', 'max:15'],
            'de_mail' => ['nullable', 'email', 'max:254'],
            'de_lgin' => $esEdicion
                ? ['nullable', 'string', 'max:50', 'regex:/^[a-z0-9_.-]+$/']
                : ['required', 'string', 'max:50', 'regex:/^[a-z0-9_.-]+$/'],
            'cdgo_prfle' => ['required', 'integer', 'exists:' . $tabla('p_prfles') . ',cdgo_prfle'],
            'cdgo_cmpnia' => ['required', 'integer', 'exists:' . $tabla('a_cmpnias') . ',cdgo_cmpnia'],
            'de_pass' => ($esEdicion && ! $cambiarClave)
                ? ['nullable']
                : ['required', 'string', 'min:4', 'same:de_pass_confirmation'],
            'de_pass_confirmation' => ($esEdicion && ! $cambiarClave)
                ? ['nullable']
                : ['required', 'string'],
            'in_esta' => ['required', 'in:0,1'],
            'cargos' => ['nullable', 'array'],
            'cargos.*' => ['integer', 'exists:' . $tabla('p_crgos') . ',cdgo_crgo'],
            'cargo_principal' => ['nullable', 'integer'],
        ], [
            'nu_docu.regex' => 'El DNI debe contener exactamente 8 números.',
            'ap_pate.regex' => 'El apellido paterno solo debe contener letras, espacios, guiones y apóstrofes.',
            'ap_mate.regex' => 'El apellido materno solo debe contener letras, espacios, guiones y apóstrofes.',
            'no_usrio.regex' => 'El nombre solo debe contener letras, espacios, guiones y apóstrofes.',
            'de_lgin.regex' => 'El usuario de login solo debe contener minúsculas, números, puntos, guiones y guiones bajos (sin espacios).',
            'de_pass.min' => 'La contraseña debe tener al menos 4 caracteres.',
            'de_pass.required' => 'Debe ingresar la contraseña.',
            'de_pass.same' => 'La contraseña y su confirmación no coinciden.',
            'de_pass_confirmation.required' => 'Debe repetir la contraseña.',
            'in_esta.required' => 'Debe indicar si el usuario está activo o inactivo.',
            'in_esta.in' => 'El estado de acceso no es válido.',
        ]);

        $nuTele = trim((string) $request->input('nu_tele', ''));

        $cdgo_lgin = $request->input('cdgo_lgin');
        $currentUser = Auth::user();
        $creatorId = $currentUser ? $currentUser->cdgo_lgin : 1;

        $dni = $request->input('nu_docu');
        $apPaterno = strtoupper($this->sanitizeText($request->input('ap_pate')));
        $apMaterno = strtoupper($this->sanitizeText($request->input('ap_mate')));
        $nombres = strtoupper($this->sanitizeText($request->input('no_usrio')));
        $nombreCorto = $nombres . ' ' . $apPaterno . ' ' . $apMaterno;

        DB::beginTransaction();

        try {
            // 1. Verificar si existe el usuario en p_usrios por DNI
            $p_usrio = DB::table($schema . 'p_usrios')->where('nu_docu', $dni)->first();
            
            $usrioData = [
                'no_crto' => $nombreCorto,
                'no_crto_sstma' => $nombreCorto,
                'de_dire' => strtoupper($this->sanitizeText($request->input('de_dire', ''))),
                'nu_tele' => $nuTele,
                'co_ubig' => '150101', // Ubigeo por defecto
                'de_mail' => strtolower(preg_replace('/\s+/', '', $request->input('de_mail', ''))),
                'nu_docu' => $dni,
                'de_ocup_rubr' => '',
                'in_tipo_pers' => '0', // Natural
                'nu_ruc' => '',
            ];

            if ($p_usrio) {
                $cdgo_usrios = $p_usrio->cdgo_usrios;
                DB::table($schema . 'p_usrios')->where('cdgo_usrios', $cdgo_usrios)->update($usrioData);
            } else {
                $usrioData['in_esta'] = '1';
                $cdgo_usrios = DB::table($schema . 'p_usrios')->insertGetId($usrioData, 'cdgo_usrios');
            }

            // 2. Registrar/actualizar en h_usrios_ntral
            $ntralData = [
                'cdgo_usrios' => $cdgo_usrios,
                'cdgo_estdo_cvil' => '1', // Soltero por defecto
                'cdgo_dcmntos_iden' => '1', // DNI
                'ap_pate' => $apPaterno,
                'ap_mate' => $apMaterno,
                'no_usrio' => $nombres,
                'nu_edad' => '0',
                'in_sexo' => '1',
                'co_usua_modi' => $creatorId,
                'ts_usua_modi' => now(),
            ];

            $ntralEx = DB::table($schema . 'h_usrios_ntral')->where('cdgo_usrios', $cdgo_usrios)->exists();
            if ($ntralEx) {
                DB::table($schema . 'h_usrios_ntral')->where('cdgo_usrios', $cdgo_usrios)->update($ntralData);
            } else {
                DB::table($schema . 'h_usrios_ntral')->insert($ntralData);
            }

            // 3. Registrar en p_usrios_cmplños si no existe
            $cumpleEx = DB::table($schema . 'p_usrios_cmplños')->where('cdgo_usrios', $cdgo_usrios)->exists();
            if (!$cumpleEx) {
                DB::table($schema . 'p_usrios_cmplños')->insert([
                    'cdgo_usrios' => $cdgo_usrios,
                    'in_esta' => '1'
                ]);
            }

            // 4. Crear o actualizar la credencial de login p_lgin
            $loginData = [
                'cdgo_prfle' => $request->input('cdgo_prfle'),
                'cdgo_usrios' => $cdgo_usrios,
                'in_esta' => $in_esta,
                'ts_usua_modi' => now(),
                'co_usua_modi' => $creatorId,
            ];

            if (!$cdgo_lgin) {
                $loginData['de_lgin'] = strtolower(preg_replace('/\s+/', '', $request->input('de_lgin')));
            }

            $claveNueva = trim((string) $request->input('de_pass', ''));
            $claveConf = trim((string) $request->input('de_pass_confirmation', ''));
            if ($cambiarClave && $claveNueva !== '' && $claveNueva === $claveConf) {
                // Almacenar con hash SHA1 (compatibilidad con el sistema actual)
                // TODO bcrypt: SigtramPassword::hash(...) cuando se active la migración
                $loginData['de_pass'] = sha1($claveNueva);
            }

            if ($cdgo_lgin) {
                DB::table($schema . 'p_lgin')->where('cdgo_lgin', $cdgo_lgin)->update($loginData);
                $message = 'Usuario actualizado correctamente.';
            } else {
                $loginData['cdgo_crgo'] = null;
                $cdgo_lgin = DB::table($schema . 'p_lgin')->insertGetId($loginData, 'cdgo_lgin');
                $message = 'Usuario registrado correctamente.';
            }

            DB::table($schema . 'p_lgin')->where('cdgo_lgin', $cdgo_lgin)->update([
                'in_esta' => $in_esta,
            ]);

            // 5. Asignar compañía en r_cmpnias_lgin
            $compEx = DB::table($schema . 'r_cmpnias_lgin')
                ->where('cdgo_lgin', $cdgo_lgin)
                ->where('cdgo_cmpnia', $request->input('cdgo_cmpnia'))
                ->exists();

            if (!$compEx) {
                DB::table($schema . 'r_cmpnias_lgin')->insert([
                    'cdgo_lgin' => $cdgo_lgin,
                    'cdgo_cmpnia' => $request->input('cdgo_cmpnia'),
                    'ts_usua_modi' => now(),
                    'in_esta' => '1'
                ]);
            }

            // 6. Sincronizar cargos del usuario
            $cargos = $request->input('cargos', []);
            $cargoPrincipal = $request->input('cargo_principal');

            // Eliminar cargos anteriores del login
            DB::table($schema . 'r_crgos_lgin')->where('cdgo_lgin', $cdgo_lgin)->delete();

            // Insertar nuevos cargos
            $primaryArea = null;
            foreach ($cargos as $cId) {
                $isPrimary = ((int)$cId === (int)$cargoPrincipal) ? '1' : '2';
                
                DB::table($schema . 'r_crgos_lgin')->insert([
                    'cdgo_lgin' => $cdgo_lgin,
                    'cdgo_crgo' => $cId,
                    'in_crgo_prmro' => $isPrimary,
                    'ts_usua_modi' => now(),
                    'in_esta' => '1'
                ]);

                if ($isPrimary === '1') {
                    $primaryArea = DB::table($schema . 'p_crgos')->where('cdgo_crgo', $cId)->value('cdgo_area');
                }
            }

            // Si no se definió cargo principal pero hay cargos, marcar el primero como principal
            if (!$primaryArea && count($cargos) > 0) {
                $firstCargo = $cargos[0];
                DB::table($schema . 'r_crgos_lgin')
                    ->where('cdgo_lgin', $cdgo_lgin)
                    ->where('cdgo_crgo', $firstCargo)
                    ->update(['in_crgo_prmro' => '1']);
                $primaryArea = DB::table($schema . 'p_crgos')->where('cdgo_crgo', $firstCargo)->value('cdgo_area');
            }

            // Actualizar área de cumpleaños (una sola fila; unique cdgo_usrios+cdgo_area)
            $this->sincronizarCumpleanos(
                $schema,
                $cdgo_usrios,
                $primaryArea,
                count($cargos) > 0
            );

            DB::commit();
            return redirect()->route('seguridad.usuario')->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->withErrors(['error' => 'Ocurrió un error al procesar el usuario: ' . $e->getMessage()]);
        }
    }
}
