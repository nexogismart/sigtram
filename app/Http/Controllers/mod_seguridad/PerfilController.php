<?php

namespace App\Http\Controllers\mod_seguridad;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PerfilController extends Controller
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
        // Trim and collapse multiple spaces into one
        return preg_replace('/\s+/', ' ', trim($value));
    }

    public function index(Request $request)
    {
        $schema = $this->getSchema();
        $query = DB::table($schema . 'p_prfles')->where('in_esta', '1');

        if ($request->filled('criterio') && $request->filled('campo')) {
            $campo = $request->input('campo');
            $criterio = trim($request->input('criterio'));
            
            if (in_array($campo, ['nc_prfle', 'de_prfle'])) {
                $query->where($campo, 'like', '%' . strtoupper($this->sanitizeText($criterio)) . '%');
            }
        }

        $perfiles = $query->orderBy('nc_prfle', 'asc')->paginate(10);

        if ($request->ajax()) {
            return response()->json($perfiles);
        }

        return view('mod_seguridad.seguridad-perfiles', compact('perfiles'));
    }

    public function store(Request $request)
    {
        $schema = $this->getSchema();
        $request->validate([
            'cdgo_prfle' => ['nullable', 'integer', 'exists:' . DB::getDefaultConnection() . '.' . $schema . 'p_prfles,cdgo_prfle'],
            'nc_prfle' => ['required', 'string', 'max:100'],
            'de_prfle' => ['required', 'string', 'max:250'],
        ]);

        $id = $request->input('cdgo_prfle');

        $data = [
            'nc_prfle' => strtoupper($this->sanitizeText($request->input('nc_prfle'))),
            'de_prfle' => strtoupper($this->sanitizeText($request->input('de_prfle'))),
            'in_esta' => '1',
        ];

        if ($id) {
            DB::table($schema . 'p_prfles')
                ->where('cdgo_prfle', $id)
                ->update($data);
            $message = 'Perfil actualizado correctamente.';
        } else {
            DB::table($schema . 'p_prfles')->insert($data);
            $message = 'Perfil registrado correctamente.';
        }

        return redirect()->route('seguridad.perfiles')->with('success', $message);
    }

    public function destroy($id)
    {
        $schema = $this->getSchema();

        // Verificar si el perfil está asignado a algún usuario activo
        $assigned = DB::table($schema . 'p_lgin')
            ->where('cdgo_prfle', $id)
            ->where('in_esta', '1')
            ->exists();

        if ($assigned) {
            return redirect()->route('seguridad.perfiles')->with('error', 'No se puede inactivar este perfil porque tiene usuarios activos asignados.');
        }

        DB::table($schema . 'p_prfles')
            ->where('cdgo_prfle', $id)
            ->update(['in_esta' => '0']);

        return redirect()->route('seguridad.perfiles')->with('success', 'Perfil eliminado correctamente.');
    }
}
