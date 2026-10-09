<?php

namespace App\Services;

use App\Models\DtoTramite;
use App\Models\TramiteDocumento;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class NotificarPlataformaService
{
    /**
     * Procesa la notificación de un expediente con control de concurrencia y transaccionalidad.
     */
    public function notificar(int $cdgoDtoTrmte, string $fecha, ?string $observaciones, ?UploadedFile $file, string $ip): ?string
    {
        $schema = rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.').'.';

        return DB::transaction(function () use ($cdgoDtoTrmte, $fecha, $observaciones, $file, $ip, $schema) {
            // 1. Obtener y bloquear el expediente para evitar concurrencia simultánea (Race Conditions)
            $expediente = DtoTramite::where('cdgo_dto_trmte', $cdgoDtoTrmte)
                ->lockForUpdate()
                ->first();

            if (! $expediente) {
                throw new \Exception('El expediente no existe.');
            }

            // 2. Verificar si ya se encuentra notificado (evitar doble notificación)
            $movimientoActivo = TramiteDocumento::where('cdgo_dto_trmte', $cdgoDtoTrmte)
                ->where('in_esta', '1')
                ->first();

            if ($movimientoActivo && (int) $movimientoActivo->cdgo_estdo_trmte === 26) {
                throw new \Exception('El expediente ya ha sido notificado anteriormente.');
            }

            $anio = date('Y');
            $mes = date('m');
            $filename = '';
            $urlImag = null;

            if ($file) {
                $extension = $file->getClientOriginalExtension();
                $filename = "NOTIF_{$cdgoDtoTrmte}_".time().'.'.$extension;
                $subPath = "adjuntos/notificaciones/{$anio}/{$mes}";
                $path = $file->storeAs($subPath, $filename, 'public');

                if (! $path) {
                    throw new \Exception('Error al guardar el archivo físico en el servidor.');
                }

                $urlImag = "notificaciones/{$anio}/{$mes}/".$filename;
            }

            $cargoId = (int) session('sigtram_cargo_id', 0);
            $userId = (int) (Auth::user()->cdgo_lgin ?? 0);
            if ($cargoId <= 0 && $movimientoActivo) {
                $cargoId = (int) ($movimientoActivo->cdgo_crgo_en ?? 0);
            }

            TramiteDocumento::where('cdgo_dto_trmte', $cdgoDtoTrmte)
                ->update(['in_esta' => '0']);

            $nuevoMovimiento = new TramiteDocumento;
            $nuevoMovimiento->cdgo_dto_trmte = $cdgoDtoTrmte;
            $nuevoMovimiento->cdgo_estdo_trmte = 26;
            $nuevoMovimiento->in_esta = '1';
            $nuevoMovimiento->de_obse = $observaciones ?: '';
            $nuevoMovimiento->de_urls_imag = $urlImag;
            $nuevoMovimiento->fe_rcbdo_trmte = $fecha;
            $nuevoMovimiento->cdgo_crgo_en = $cargoId;
            $nuevoMovimiento->cdgo_crgo_sa = $cargoId;
            $nuevoMovimiento->cdgo_lgin = $userId;
            $nuevoMovimiento->save();

            $ultimoCargoTrmte = DB::table($schema.'r_dtos_crgos_trmtes')
                ->where('cdgo_dto_trmte', $cdgoDtoTrmte)
                ->where('in_esta', '1')
                ->orderBy('cdgo_dto_crgo_trmte', 'desc')
                ->first();

            if ($ultimoCargoTrmte) {
                DB::table($schema.'r_dtos_crgos_trmtes')
                    ->where('cdgo_dto_crgo_trmte', $ultimoCargoTrmte->cdgo_dto_crgo_trmte)
                    ->update([
                        'fe_dto_crgo_fin' => now(),
                    ]);
            }

            // 8. Registro de bitácora en la BD legacy (p_sgmto_log) - Degradación Elegante
            try {
                DB::table($schema.'p_sgmto_log')->insert([
                    'cdgo_lgin' => $userId,
                    'de_sgto_log' => "Se notifico el expediente ID {$cdgoDtoTrmte} con el cargo {$filename}",
                    'fe_sgto_log' => now(),
                    'de_ip' => $ip,
                ]);
            } catch (\Exception $e) {
                Log::warning("No se pudo registrar la bitácora legacy (p_sgmto_log) para el exp. {$cdgoDtoTrmte}: ".$e->getMessage());
            }

            // 9. Registro en los logs diarios de Laravel para auditoría - Degradación Elegante
            try {
                Log::channel('daily')->info("Usuario ID {$userId} notificó expediente ID {$cdgoDtoTrmte} - Archivo: {$filename} - IP: {$ip}");
            } catch (\Exception $e) {
                // Silencioso, el fallo en logs diarios no debe detener la transacción principal
            }

            return $nuevoMovimiento->de_urls_imag;
        });
    }
}
