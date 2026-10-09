<?php

namespace App\Http\Controllers\mod_seguridad;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class AccesoController extends Controller
{
    private function getSchema(): string
    {
        return rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';
    }

    public function index(Request $request)
    {
        $schema = $this->getSchema();

        // Usuarios sin acceso (in_esta = '0')
        $usuariosSinAcceso = DB::table($schema . 'p_lgin as l')
            ->join($schema . 'p_usrios as u', 'l.cdgo_usrios', '=', 'u.cdgo_usrios')
            ->select('l.cdgo_lgin', 'u.no_crto', 'u.nu_docu', 'l.de_lgin')
            ->where('l.in_esta', '0')
            ->orderBy('u.no_crto', 'asc')
            ->get();

        // Usuarios con acceso (in_esta = '1')
        $usuariosConAcceso = DB::table($schema . 'p_lgin as l')
            ->join($schema . 'p_usrios as u', 'l.cdgo_usrios', '=', 'u.cdgo_usrios')
            ->join($schema . 'p_prfles as p', 'l.cdgo_prfle', '=', 'p.cdgo_prfle')
            ->select('l.cdgo_lgin', 'u.no_crto', 'u.nu_docu', 'p.nc_prfle', 'l.de_lgin')
            ->where('l.in_esta', '1')
            ->orderBy('u.no_crto', 'asc')
            ->get();

        // Listado de áreas
        $areas = DB::table($schema . 'a_areas')
            ->where('in_esta', '1')
            ->orderBy('de_area', 'asc')
            ->get();

        return view('mod_seguridad.seguridad-accesos', compact('usuariosSinAcceso', 'usuariosConAcceso', 'areas'));
    }

    public function toggleAcceso(Request $request)
    {
        $schema = $this->getSchema();
        $request->validate([
            'cdgo_lgin' => ['required', 'integer', 'exists:' . DB::getDefaultConnection() . '.' . $schema . 'p_lgin,cdgo_lgin'],
            'in_esta' => ['required', 'in:0,1'],
        ]);

        $loginId = $request->input('cdgo_lgin');
        $in_esta = $request->input('in_esta');

        DB::table($schema . 'p_lgin')
            ->where('cdgo_lgin', $loginId)
            ->update([
                'in_esta' => $in_esta,
                'ts_usua_modi' => now()
            ]);

        // Si se deshabilita el acceso, y no tiene cargos, desactivar cumpleaños
        if ($in_esta === '0') {
            $cdgo_usrios = DB::table($schema . 'p_lgin')->where('cdgo_lgin', $loginId)->value('cdgo_usrios');
            $hasCargos = DB::table($schema . 'r_crgos_lgin')->where('cdgo_lgin', $loginId)->exists();
            if (!$hasCargos && $cdgo_usrios) {
                $this->sincronizarCumpleanos($schema, $cdgo_usrios, null, false);
            }
        }

        return response()->json(['success' => true, 'message' => 'Acceso modificado correctamente.']);
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

    public function cargosArea($areaId)
    {
        $schema = $this->getSchema();
        
        $validator = \Illuminate\Support\Facades\Validator::make(['areaId' => $areaId], [
            'areaId' => ['required', 'string', 'exists:' . DB::getDefaultConnection() . '.' . $schema . 'a_areas,cdgo_area']
        ]);
        
        if ($validator->fails()) {
            return response()->json(['error' => 'Área inválida o no existente.'], 422);
        }

        $cargos = DB::table($schema . 'p_crgos')
            ->where('cdgo_area', $areaId)
            ->where('in_esta', '1')
            ->orderBy('de_crgo', 'asc')
            ->get();

        return response()->json($cargos);
    }

    public function cargosUsuario($loginId)
    {
        $schema = $this->getSchema();

        $validator = \Illuminate\Support\Facades\Validator::make(['loginId' => $loginId], [
            'loginId' => ['required', 'integer', 'exists:' . DB::getDefaultConnection() . '.' . $schema . 'p_lgin,cdgo_lgin']
        ]);
        
        if ($validator->fails()) {
            return response()->json(['error' => 'Usuario inválido o no existente.'], 422);
        }

        $cargos = DB::table($schema . 'r_crgos_lgin as r')
            ->join($schema . 'p_crgos as c', 'r.cdgo_crgo', '=', 'c.cdgo_crgo')
            ->join($schema . 'a_areas as a', 'c.cdgo_area', '=', 'a.cdgo_area')
            ->select('r.cdgo_crgo_lgin', 'r.in_crgo_prmro', 'c.cdgo_crgo', 'c.de_crgo', 'a.de_area', 'c.in_nvel')
            ->where('r.cdgo_lgin', $loginId)
            ->orderBy('c.in_nvel', 'asc')
            ->get();

        return response()->json($cargos);
    }

    public function asignarCargo(Request $request)
    {
        $schema = $this->getSchema();
        $request->validate([
            'cdgo_lgin' => ['required', 'integer', 'exists:' . DB::getDefaultConnection() . '.' . $schema . 'p_lgin,cdgo_lgin'],
            'cdgo_crgo' => ['required', 'integer', 'exists:' . DB::getDefaultConnection() . '.' . $schema . 'p_crgos,cdgo_crgo'],
            'in_crgo_prmro' => ['required', 'in:1,2'],
        ]);

        $loginId = $request->input('cdgo_lgin');
        $cargoId = $request->input('cdgo_crgo');
        $in_crgo_prmro = $request->input('in_crgo_prmro');

        // Validar si ya está asignado
        $exists = DB::table($schema . 'r_crgos_lgin')
            ->where('cdgo_lgin', $loginId)
            ->where('cdgo_crgo', $cargoId)
            ->exists();

        if ($exists) {
            return response()->json(['success' => false, 'message' => 'El cargo ya se encuentra asignado a este usuario.']);
        }

        DB::beginTransaction();

        try {
            // Si se asigna como principal, quitar principal del resto
            if ($in_crgo_prmro === '1') {
                DB::table($schema . 'r_crgos_lgin')
                    ->where('cdgo_lgin', $loginId)
                    ->update(['in_crgo_prmro' => '2']);
            }

            // Insertar cargo relación
            DB::table($schema . 'r_crgos_lgin')->insert([
                'cdgo_lgin' => $loginId,
                'cdgo_crgo' => $cargoId,
                'in_crgo_prmro' => $in_crgo_prmro,
                'ts_usua_modi' => now(),
                'in_esta' => '1'
            ]);

            // Obtener datos de área y usuario para actualizar cumpleaños/área
            $cdgo_usrios = DB::table($schema . 'p_lgin')->where('cdgo_lgin', $loginId)->value('cdgo_usrios');
            $cdgo_area = DB::table($schema . 'p_crgos')->where('cdgo_crgo', $cargoId)->value('cdgo_area');

            if ($cdgo_usrios && $cdgo_area) {
                $this->sincronizarCumpleanos($schema, $cdgo_usrios, $cdgo_area, true);
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Cargo asignado correctamente.']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Error al asignar cargo: ' . $e->getMessage()]);
        }
    }

    public function eliminarCargo(Request $request)
    {
        $schema = $this->getSchema();
        $request->validate([
            'cdgo_crgo_lgin' => ['required', 'integer', 'exists:' . DB::getDefaultConnection() . '.' . $schema . 'r_crgos_lgin,cdgo_crgo_lgin'],
        ]);

        $id = $request->input('cdgo_crgo_lgin');

        $cargoLgin = DB::table($schema . 'r_crgos_lgin')->where('cdgo_crgo_lgin', $id)->first();
        if (!$cargoLgin) {
            return response()->json(['success' => false, 'message' => 'Asignación no encontrada.']);
        }

        $loginId = $cargoLgin->cdgo_lgin;

        DB::beginTransaction();

        try {
            DB::table($schema . 'r_crgos_lgin')->where('cdgo_crgo_lgin', $id)->delete();

            // Verificar si le quedan cargos
            $hasRemaining = DB::table($schema . 'r_crgos_lgin')->where('cdgo_lgin', $loginId)->exists();
            if (!$hasRemaining) {
                $cdgo_usrios = DB::table($schema . 'p_lgin')->where('cdgo_lgin', $loginId)->value('cdgo_usrios');
                if ($cdgo_usrios) {
                    $this->sincronizarCumpleanos($schema, $cdgo_usrios, null, false);
                }
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Cargo retirado correctamente.']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Error al retirar cargo: ' . $e->getMessage()]);
        }
    }

    public function cambiarCargoPrincipal(Request $request)
    {
        $schema = $this->getSchema();
        $request->validate([
            'cdgo_crgo_lgin' => ['required', 'integer', 'exists:' . DB::getDefaultConnection() . '.' . $schema . 'r_crgos_lgin,cdgo_crgo_lgin'],
            'cdgo_lgin' => ['required', 'integer', 'exists:' . DB::getDefaultConnection() . '.' . $schema . 'p_lgin,cdgo_lgin'],
        ]);

        $id = $request->input('cdgo_crgo_lgin');
        $loginId = $request->input('cdgo_lgin');

        DB::beginTransaction();

        try {
            // Resetear todos a secundario ('2')
            DB::table($schema . 'r_crgos_lgin')
                ->where('cdgo_lgin', $loginId)
                ->update(['in_crgo_prmro' => '2']);

            // Marcar el seleccionado como principal ('1')
            DB::table($schema . 'r_crgos_lgin')
                ->where('cdgo_crgo_lgin', $id)
                ->update(['in_crgo_prmro' => '1']);

            // Actualizar área de cumpleaños
            $cargoId = DB::table($schema . 'r_crgos_lgin')->where('cdgo_crgo_lgin', $id)->value('cdgo_crgo');
            $cdgo_area = DB::table($schema . 'p_crgos')->where('cdgo_crgo', $cargoId)->value('cdgo_area');
            $cdgo_usrios = DB::table($schema . 'p_lgin')->where('cdgo_lgin', $loginId)->value('cdgo_usrios');

            if ($cdgo_usrios && $cdgo_area) {
                $this->sincronizarCumpleanos($schema, $cdgo_usrios, $cdgo_area, true);
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Jefe de Unidad Orgánica actualizado.']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Error al cambiar cargo principal: ' . $e->getMessage()]);
        }
    }
}
