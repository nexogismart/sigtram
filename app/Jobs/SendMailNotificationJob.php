<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

/**
 * Class SendMailNotificationJob
 *
 * Trabajo en cola (Redis) para procesar el envío asíncrono de correos electrónicos.
 */
class SendMailNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var string
     */
    protected string $correo;

    /**
     * @var string
     */
    protected string $asunto;

    /**
     * @var string
     */
    protected string $vista;

    /**
     * @var array
     */
    protected array $datos;

    /**
     * Create a new job instance.
     *
     * @param string $correo
     * @param string $asunto
     * @param string $vista
     * @param array $datos
     */
    public function __construct(string $correo, string $asunto, string $vista, array $datos = [])
    {
        $this->correo = $correo;
        $this->asunto = $asunto;
        $this->vista = $vista;
        $this->datos = $datos;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        try {
            Mail::send($this->vista, $this->datos, function ($message) {
                $message->to($this->correo)
                    ->subject($this->asunto);
            });

            Log::channel('notifications')->info("[ENVIO_EXITO] [EMAIL] Destinatario: {$this->correo} | Asunto: {$this->asunto}");
        } catch (\Throwable $e) {
            Log::channel('notifications')->error("[FALLIDO] [EMAIL] Destinatario: {$this->correo} | Asunto: {$this->asunto} | Error: " . $e->getMessage());
            throw $e; // Volvemos a lanzar la excepción para que Laravel reintente el job si está configurado
        }
    }
}
