<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ChangePasswordCodeMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public string $code,
        public string $username
    ) {
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject("Código de Verificación - Cambio de Contraseña SIGTRAM")
            ->view('emails.change_password_code');
    }
}
