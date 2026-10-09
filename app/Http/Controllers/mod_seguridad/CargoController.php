<?php

namespace App\Http\Controllers\mod_seguridad;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class CargoController extends Controller
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

    public function index(Request $request)
    {
        $schema = $this->getSchema();
        $query = DB::table($schema . 'p_crgos as c')
            ->join($schema . 'a_areas as a', 'c.cdgo_area', '=', 'a.cdgo_area')
            ->select('c.*', 'a.de_area')
            ->where('c.in_esta', '1');

        if ($request->filled('criterio') && $request->filled('campo')) {
            $campo = $request->input('campo');
            $criterio = trim($request->input('criterio'));
            
            if ($campo === 'de_crgo') {
                $query->where('c.de_crgo', 'like', '%' . strtoupper($this->sanitizeText($criterio)) . '%');
            } elseif ($campo === 'de_area') {
                $query->where('a.de_area', 'like', '%' . strtoupper($this->sanitizeText($criterio)) . '%');
            }
        }

        $cargos = $query->orderBy('c.de_crgo', 'asc')->paginate(10);
        $areas = DB::table($schema . 'a_areas')
            ->where('in_esta', '1')
            ->orderBy('de_area', 'asc')
            ->get();

        if ($request->ajax()) {
            return response()->json([
                'cargos' => $cargos,
                'areas' => $areas
            ]);
        }

        return view('mod_seguridad.seguridad-cargos', compact('cargos', 'areas'));
    }

    public function store(Request $request)
    {
        $schema = $this->getSchema();
        $request->validate([
            'cdgo_crgo' => ['nullable', 'integer', 'exists:' . DB::getDefaultConnection() . '.' . $schema . 'p_crgos,cdgo_crgo'],
            'dc_crgo' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9_]+$/'],
            'de_crgo' => ['required', 'string', 'max:250'],
            'cdgo_area' => ['required', 'string', 'exists:' . DB::getDefaultConnection() . '.' . $schema . 'a_areas,cdgo_area'],
        ], [
            'dc_crgo.regex' => 'La abreviatura solo debe contener caracteres alfanuméricos y guiones bajos (sin espacios).'
        ]);

        $id = $request->input('cdgo_crgo');
        $user = Auth::user();

        $data = [
            'dc_crgo' => strtoupper($this->sanitizeText($request->input('dc_crgo'))),
            'de_crgo' => strtoupper($this->sanitizeText($request->input('de_crgo'))),
            'cdgo_area' => $request->input('cdgo_area'),
            'nu_orde' => 2,
            'co_usua_modi' => $user ? $user->cdgo_lgin : 1,
            'ts_usua_modi' => now(),
            'in_esta' => '1',
        ];

        if ($id) {
            DB::table($schema . 'p_crgos')
                ->where('cdgo_crgo', $id)
                ->update($data);
            $message = 'Cargo actualizado correctamente.';
        } else {
            DB::table($schema . 'p_crgos')->insert($data);
            $message = 'Cargo registrado correctamente.';
        }

        return redirect()->route('seguridad.cargos')->with('success', $message);
    }

    public function destroy($id)
    {
        $schema = $this->getSchema();

        // Verificar si el cargo está asignado a algún usuario
        $assigned = DB::table($schema . 'r_crgos_lgin')
            ->where('cdgo_crgo', $id)
            ->where('in_esta', '1')
            ->exists();

        if ($assigned) {
            return redirect()->route('seguridad.cargos')->with('error', 'No se puede inactivar este cargo porque está asignado a usuarios.');
        }

        DB::table($schema . 'p_crgos')
            ->where('cdgo_crgo', $id)
            ->update(['in_esta' => '0']);

        return redirect()->route('seguridad.cargos')->with('success', 'Cargo eliminado correctamente.');
    }
}
