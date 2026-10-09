<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ExpedienteFinalizadoMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $expediente;
    public $observaciones;
    public $area;
    public $filePath;
    public $fileName;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($expediente, string $observaciones, string $area, ?string $filePath = null, ?string $fileName = null)
    {
        $this->expediente = $expediente;
        $this->observaciones = $observaciones;
        $this->area = $area;
        $this->filePath = $filePath;
        $this->fileName = $fileName;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $email = $this->subject("Expediente N° {$this->expediente->nu_expe_todo} Finalizado")
            ->view('emails.expediente_finalizado');

        if ($this->filePath && file_exists($this->filePath)) {
            $email->attach($this->filePath, [
                'as' => $this->fileName ?: basename($this->filePath),
            ]);
        }

        return $email;
    }
}
