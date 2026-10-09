<?php

namespace App\Http\Controllers\mod_seguridad;

use App\Http\Controllers\Controller;
use App\Mail\ChangePasswordCodeMail;
// use App\Support\SigtramPassword; // migración SHA1→bcrypt (desactivada por ahora)
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PasswordController extends Controller
{
    private function getSchema(): string
    {
        return rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $email = $user->usrio?->de_mail;
        
        $maskedEmail = '';
        if ($email) {
            $parts = explode('@', $email);
            if (count($parts) === 2) {
                $local = $parts[0];
                $domain = $parts[1];
                $len = strlen($local);
                if ($len > 3) {
                    $maskedEmail = substr($local, 0, 1) . str_repeat('*', $len - 2) . substr($local, -1) . '@' . $domain;
                } else {
                    $maskedEmail = substr($local, 0, 1) . str_repeat('*', $len - 1) . '@' . $domain;
                }
            } else {
                $maskedEmail = $email;
            }
        }

        return view('mod_seguridad.cambiar-contraseña', compact('maskedEmail'));
    }

    public function enviarCodigo(Request $request)
    {
        try {
            $request->validate([
                'password_actual' => ['required', 'string'],
                'nueva_contrasena' => [
                    'required',
                    'string',
                    'min:8',
                    'regex:/[a-z]/',
                    'regex:/[A-Z]/',
                    'regex:/[0-9]/',
                    'regex:/[@$!%*?&#.]/',
                    'different:password_actual'
                ],
                'confirmar_nueva_contrasena' => ['required', 'string', 'same:nueva_contrasena']
            ], [
                'nueva_contrasena.min' => 'La nueva contraseña debe tener al menos 8 caracteres.',
                'nueva_contrasena.regex' => 'La nueva contraseña debe incluir al menos una mayúscula, una minúscula, un número y un símbolo especial.',
                'nueva_contrasena.different' => 'La nueva contraseña debe ser diferente de la contraseña actual.',
                'confirmar_nueva_contrasena.same' => 'Las contraseñas no coinciden.'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'errors' => $e->errors()
            ], 422);
        }

        $user = Auth::user();
        // TODO bcrypt: SigtramPassword::check(...) en lugar de solo sha1
        if (sha1($request->password_actual) !== $user->de_pass) {
            return response()->json([
                'status' => 'error',
                'errors' => [
                    'password_actual' => ['La contraseña actual es incorrecta.']
                ]
            ], 422);
        }

        $email = $user->usrio?->de_mail;
        if (!$email) {
            return response()->json([
                'status' => 'error',
                'message' => 'No tienes un correo electrónico registrado en tu perfil de usuario. Contacta con el administrador del sistema.'
            ], 400);
        }

        $code = (string) rand(100000, 999999);
        session([
            'pw_change_code' => $code,
            'pw_change_expiry' => now()->addMinutes(10),
        ]);

        try {
            Mail::to($email)->send(new ChangePasswordCodeMail($code, $user->nombreMostrar()));
        } catch (\Exception $e) {
            Log::error('Error al enviar correo de cambio de contraseña: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al enviar el correo con el código de verificación: ' . $e->getMessage()
            ], 500);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'El código de verificación ha sido enviado a tu correo electrónico registrado.'
        ]);
    }

    public function confirmar(Request $request)
    {
        $request->validate([
            'password_actual' => ['required', 'string'],
            'nueva_contrasena' => [
                'required',
                'string',
                'min:8',
                'regex:/[a-z]/',
                'regex:/[A-Z]/',
                'regex:/[0-9]/',
                'regex:/[@$!%*?&#.]/'
            ],
            'confirmar_nueva_contrasena' => ['required', 'string', 'same:nueva_contrasena'],
            'codigo_verificacion' => ['required', 'string', 'digits:6']
        ], [
            'nueva_contrasena.min' => 'La nueva contraseña debe tener al menos 8 caracteres.',
            'nueva_contrasena.regex' => 'La nueva contraseña debe incluir al menos una mayúscula, una minúscula, un número y un símbolo especial.',
            'confirmar_nueva_contrasena.same' => 'Las contraseñas no coinciden.',
            'codigo_verificacion.required' => 'El código de verificación es obligatorio.',
            'codigo_verificacion.digits' => 'El código debe tener exactamente 6 dígitos.'
        ]);

        $user = Auth::user();

        // Verificar contraseña actual (SHA1)
        // TODO bcrypt: SigtramPassword::check(...) en lugar de solo sha1
        if (sha1($request->password_actual) !== $user->de_pass) {
            return back()->withErrors(['password_actual' => 'La contraseña actual es incorrecta.'])->withInput();
        }

        // Verificar código
        $sessionCode = session('pw_change_code');
        $sessionExpiry = session('pw_change_expiry');

        if (!$sessionCode || !$sessionExpiry || now()->greaterThan($sessionExpiry)) {
            return back()->withErrors(['codigo_verificacion' => 'El código de verificación ha expirado o no es válido. Por favor, solicita uno nuevo.'])->withInput();
        }

        if ($request->codigo_verificacion !== $sessionCode) {
            return back()->withErrors(['codigo_verificacion' => 'El código de verificación ingresado es incorrecto.'])->withInput();
        }

        // Actualizar contraseña
        try {
            $schema = $this->getSchema();
            DB::table($schema . 'p_lgin')
                ->where('cdgo_lgin', $user->cdgo_lgin)
                ->update([
                    // TODO bcrypt: SigtramPassword::hash(...) cuando se active la migración
                    'de_pass' => sha1($request->nueva_contrasena),
                    'ts_usua_modi' => now(),
                    'co_usua_modi' => $user->cdgo_lgin
                ]);

            // Limpiar variables de sesión
            session()->forget(['pw_change_code', 'pw_change_expiry']);

            return redirect()->route('inicio')->with('success', '¡Tu contraseña ha sido cambiada correctamente!');
        } catch (\Exception $e) {
            Log::error('Error al actualizar contraseña en BD: ' . $e->getMessage());
            return back()->withErrors(['error' => 'No se pudo actualizar la contraseña en la base de datos: ' . $e->getMessage()])->withInput();
        }
    }
}
