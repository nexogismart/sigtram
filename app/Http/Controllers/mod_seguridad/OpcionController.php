<?php

namespace App\Http\Controllers\mod_seguridad;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OpcionController extends Controller
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
        $query = DB::table($schema . 'a_rles as r')
            ->join($schema . 'a_mdlos as m', 'r.cdgo_mdlo', '=', 'm.cdgo_mdlo')
            ->leftJoin($schema . 'a_rles as parent', 'r.cdgo_sub_rle', '=', 'parent.cdgo_rle')
            ->select('r.*', 'm.de_mdlo', 'parent.dc_rle as de_sub_rle_nombre')
            ->where('r.in_esta', '1');

        if ($request->filled('criterio') && $request->filled('campo')) {
            $campo = $request->input('campo');
            $criterio = trim($request->input('criterio'));
            
            if ($campo === 'dc_rle') {
                $query->where('r.dc_rle', 'like', '%' . strtoupper($this->sanitizeText($criterio)) . '%');
            } elseif ($campo === 'de_mdlo') {
                $query->where('m.de_mdlo', 'like', '%' . strtoupper($this->sanitizeText($criterio)) . '%');
            }
        }

        $opciones = $query->orderBy('m.de_mdlo', 'asc')->orderBy('r.dc_rle', 'asc')->paginate(10);
        $modulos = DB::table($schema . 'a_mdlos')->where('in_esta', '!=', '0')->orderBy('de_mdlo', 'asc')->get();
        $parentOpciones = DB::table($schema . 'a_rles')->where('in_esta', '1')->whereNull('cdgo_sub_rle')->orderBy('dc_rle', 'asc')->get();

        if ($request->ajax()) {
            return response()->json([
                'opciones' => $opciones,
                'modulos' => $modulos,
                'parentOpciones' => $parentOpciones
            ]);
        }

        return view('mod_seguridad.seguridad-opciones', compact('opciones', 'modulos', 'parentOpciones'));
    }

    public function store(Request $request)
    {
        $schema = $this->getSchema();
        $request->validate([
            'cdgo_rle' => ['nullable', 'integer', 'exists:' . DB::getDefaultConnection() . '.' . $schema . 'a_rles,cdgo_rle'],
            'dc_rle' => ['required', 'string', 'max:100'],
            'de_rle' => ['required', 'string', 'max:250'],
            'de_dreccon' => ['required', 'string', 'max:250'],
            'cdgo_mdlo' => ['required', 'integer', 'exists:' . DB::getDefaultConnection() . '.' . $schema . 'a_mdlos,cdgo_mdlo'],
            'in_popup' => ['nullable', 'in:1,0'],
            'cdgo_sub_rle' => ['nullable', 'integer', 'exists:' . DB::getDefaultConnection() . '.' . $schema . 'a_rles,cdgo_rle'],
        ]);

        $id = $request->input('cdgo_rle');

        // Sanitizar ruta: sin espacios y en minúsculas
        $routeSanitized = strtolower(preg_replace('/\s+/', '', $request->input('de_dreccon')));

        $data = [
            'dc_rle' => strtoupper($this->sanitizeText($request->input('dc_rle'))),
            'de_rle' => strtoupper($this->sanitizeText($request->input('de_rle'))),
            'de_dreccon' => $routeSanitized,
            'cdgo_mdlo' => $request->input('cdgo_mdlo'),
            'in_popup' => $request->input('in_popup', '0'),
            'cdgo_sub_rle' => $request->input('cdgo_sub_rle') ?: null,
            'in_esta' => '1',
        ];

        if ($id) {
            DB::table($schema . 'a_rles')
                ->where('cdgo_rle', $id)
                ->update($data);
            $message = 'Opción de menú actualizada correctamente.';
        } else {
            DB::table($schema . 'a_rles')->insert($data);
            $message = 'Opción de menú registrada correctamente.';
        }

        return redirect()->route('seguridad.opciones')->with('success', $message);
    }

    public function destroy($id)
    {
        $schema = $this->getSchema();

        // Verificar si es una opción padre que tiene subopciones de menú activas
        $hasChildren = DB::table($schema . 'a_rles')
            ->where('cdgo_sub_rle', $id)
            ->where('in_esta', '1')
            ->exists();

        if ($hasChildren) {
            return redirect()->route('seguridad.opciones')->with('error', 'No se puede inactivar esta opción porque tiene subopciones de menú activas.');
        }

        DB::table($schema . 'a_rles')
            ->where('cdgo_rle', $id)
            ->update(['in_esta' => '0']);

        return redirect()->route('seguridad.opciones')->with('success', 'Opción de menú eliminada correctamente.');
    }
}
