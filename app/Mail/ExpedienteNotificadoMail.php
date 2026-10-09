<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ExpedienteNotificadoMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public object $expediente,
        public string $observaciones,
        public string $area,
        public ?string $filePath = null,
        public ?string $fileName = null
    ) {
    }

    public function build()
    {
        $email = $this->subject("Notificacion de expediente {$this->expediente->nu_expe_todo}")
            ->view('emails.expediente_notificado');

        if ($this->filePath && file_exists($this->filePath)) {
            $email->attach($this->filePath, [
                'as' => $this->fileName ?: basename($this->filePath),
            ]);
        }

        return $email;
    }
}
