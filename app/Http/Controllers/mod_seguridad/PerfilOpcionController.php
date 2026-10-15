<?php

namespace App\Http\Controllers\mod_seguridad;

use App\Http\Controllers\Controller;
use App\Support\SessionKeys;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PerfilOpcionController extends Controller
{
    private function getSchema(): string
    {
        return rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';
    }

    /**
     * Los permisos se cachean por cdgo_lgin (8h). Al cambiar opciones del perfil,
     * hay que invalidar la caché de todos los logins con ese perfil.
     */
    private function invalidarCachePerfil(int $perfilId): void
    {
        $schema = $this->getSchema();
        $logins = DB::table($schema . 'p_lgin')
            ->where('cdgo_prfle', $perfilId)
            ->pluck('cdgo_lgin');

        foreach ($logins as $cdgoLgin) {
            Cache::forget(SessionKeys::permKey((string) $cdgoLgin));
        }
    }

    public function index(Request $request)
    {
        $schema = $this->getSchema();

        // Cargar perfiles
        $perfiles = DB::table($schema . 'p_prfles')
            ->where('in_esta', '1')
            ->orderBy('nc_prfle', 'asc')
            ->get();

        // Cargar módulos y sus opciones
        $modulos = DB::table($schema . 'a_mdlos')
            ->where('in_esta', '!=', '0')
            ->orderBy('de_mdlo', 'asc')
            ->get();

        $opciones = DB::table($schema . 'a_rles')
            ->where('in_esta', '1')
            ->orderBy('dc_rle', 'asc')
            ->get();

        // Agrupar opciones por módulo
        $opcionesPorModulo = $opciones->groupBy('cdgo_mdlo');

        return view('mod_seguridad.seguridad-perfiles-opciones', compact('perfiles', 'modulos', 'opcionesPorModulo'));
    }

    public function opcionesAsignadas($perfilId)
    {
        $schema = $this->getSchema();
        
        $validator = \Illuminate\Support\Facades\Validator::make(['perfilId' => $perfilId], [
            'perfilId' => ['required', 'integer', 'exists:' . DB::getDefaultConnection() . '.' . $schema . 'p_prfles,cdgo_prfle']
        ]);
        
        if ($validator->fails()) {
            return response()->json(['error' => 'Perfil inválido o no existente.'], 422);
        }
        
        $asignadas = DB::table($schema . 'r_prmsos as p')
            ->join($schema . 'a_rles as r', 'p.cdgo_rle', '=', 'r.cdgo_rle')
            ->where('p.cdgo_prfle', $perfilId)
            ->where('r.in_esta', '1')
            ->select('r.cdgo_rle')
            ->pluck('cdgo_rle')
            ->all();

        return response()->json($asignadas);
    }

    public function asignar(Request $request)
    {
        $schema = $this->getSchema();
        $request->validate([
            'cdgo_prfle' => ['required', 'integer', 'exists:' . DB::getDefaultConnection() . '.' . $schema . 'p_prfles,cdgo_prfle'],
            'cdgo_rle' => ['required', 'integer', 'exists:' . DB::getDefaultConnection() . '.' . $schema . 'a_rles,cdgo_rle'],
        ]);

        $perfilId = $request->input('cdgo_prfle');
        $opcionId = $request->input('cdgo_rle');

        // Validar si ya existe
        $exists = DB::table($schema . 'r_prmsos')
            ->where('cdgo_prfle', $perfilId)
            ->where('cdgo_rle', $opcionId)
            ->exists();

        if ($exists) {
            return response()->json(['success' => true, 'message' => 'El permiso ya se encuentra registrado.']);
        }

        $cmpnia = (int) session(SessionKeys::USER_COMPANY, 0);
        if ($cmpnia <= 0) {
            return response()->json(['success' => false, 'message' => 'No tiene una compañía activa válida en la sesión.'], 403);
        }

        DB::table($schema . 'r_prmsos')->insert([
            'cdgo_prfle' => $perfilId,
            'cdgo_cmpnia' => $cmpnia,
            'cdgo_rle' => $opcionId,
            'cdgo_crgo' => null,
            'nro_ordn' => 1
        ]);

        $this->invalidarCachePerfil((int) $perfilId);

        return response()->json(['success' => true, 'message' => 'Permiso asignado correctamente.']);
    }

    public function desasignar(Request $request)
    {
        $schema = $this->getSchema();
        $request->validate([
            'cdgo_prfle' => ['required', 'integer', 'exists:' . DB::getDefaultConnection() . '.' . $schema . 'p_prfles,cdgo_prfle'],
            'cdgo_rle' => ['required', 'integer', 'exists:' . DB::getDefaultConnection() . '.' . $schema . 'a_rles,cdgo_rle'],
        ]);

        $perfilId = $request->input('cdgo_prfle');
        $opcionId = $request->input('cdgo_rle');

        DB::table($schema . 'r_prmsos')
            ->where('cdgo_prfle', $perfilId)
            ->where('cdgo_rle', $opcionId)
            ->delete();

        $this->invalidarCachePerfil((int) $perfilId);

        return response()->json(['success' => true, 'message' => 'Permiso retirado correctamente.']);
    }
}
