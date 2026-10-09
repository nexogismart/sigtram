<?php

declare(strict_types=1);

use App\Contracts\Services\NotificationCoreInterface;
use App\Jobs\SendWhatsAppNotificationJob;
use App\Jobs\SendMailNotificationJob;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    // Forzar el uso del MockProvider para todos los tests para evitar llamadas cURL reales
    $this->app->instance(
        \App\Contracts\Services\WhatsAppProviderInterface::class,
        new \App\Services\Core\Notifications\WhatsApp\MockProvider()
    );

    $this->notificationCore = app(NotificationCoreInterface::class);
});

test('puede enviar whatsapp texto de forma sincrona', function () {
    Config::set('whatsapp.activo', true);

    $res = $this->notificationCore->enviarWhatsappTexto('+51999888777', 'Mensaje de prueba sincrono', false);

    expect($res)->toBeArray()
        ->and($res['success'])->toBeTrue();
});

test('puede encolar whatsapp texto de forma asincrona', function () {
    Queue::fake();
    Config::set('whatsapp.activo', true);

    $res = $this->notificationCore->enviarWhatsappTexto('+51999888777', 'Mensaje de prueba asincrono', true);

    expect($res)->toBeArray()
        ->and($res['success'])->toBeTrue()
        ->and($res['message'])->toContain('encolado');

    Queue::assertPushed(SendWhatsAppNotificationJob::class);
});

test('puede enviar whatsapp imagen de forma sincrona', function () {
    Config::set('whatsapp.activo', true);

    $res = $this->notificationCore->enviarWhatsappImagen('+51999888777', 'Imagen de prueba', 'https://example.com/img.png', false);

    expect($res)->toBeArray()
        ->and($res['success'])->toBeTrue();
});

test('puede enviar whatsapp documento de forma sincrona', function () {
    Config::set('whatsapp.activo', true);

    $res = $this->notificationCore->enviarWhatsappDocumento('+51999888777', 'Doc de prueba', 'https://example.com/doc.pdf', 'doc.pdf', false);

    expect($res)->toBeArray()
        ->and($res['success'])->toBeTrue();
});

test('respeta interruptor maestro whatsapp inactivo', function () {
    Config::set('whatsapp.activo', false);

    $res = $this->notificationCore->enviarWhatsappTexto('+51999888777', 'Mensaje omitido', false);

    expect($res)->toBeArray()
        ->and($res['success'])->toBeTrue()
        ->and($res['message'])->toContain('pausado');
});

test('puede encolar email de forma asincrona', function () {
    Queue::fake();

    $res = $this->notificationCore->enviarCorreo(
        'test@sigtram.gob.pe',
        'Asunto de prueba',
        'emails.test',
        ['key' => 'value'],
        true
    );

    expect($res)->toBeArray()
        ->and($res['success'])->toBeTrue()
        ->and($res['message'])->toContain('encolado');

    Queue::assertPushed(SendMailNotificationJob::class);
});

test('puede enviar email de forma sincrona', function () {
    // Limpiamos cualquier mensaje anterior del array transport de Laravel
    $transport = app('mailer')->getSymfonyTransport();
    
    // Si tiene un método para limpiar los mensajes, lo llamamos
    if (method_exists($transport, 'messages')) {
        // En algunas versiones, se puede resetear o leer directamente
        $beforeCount = count($transport->messages());
        
        $res = $this->notificationCore->enviarCorreo(
            'test@sigtram.gob.pe',
            'Asunto de prueba',
            'emails.test',
            ['key' => 'value'],
            false
        );

        expect($res)->toBeArray()
            ->and($res['success'])->toBeTrue();

        $messages = $transport->messages();
        expect($messages->count())->toBe($beforeCount + 1);
        
        $lastSentMessage = $messages->last();
        expect($lastSentMessage)->not->toBeNull();
        
        $email = $lastSentMessage->getOriginalMessage();
        expect($email->toString())->toContain('To: test@sigtram.gob.pe')
            ->and($email->toString())->toContain('Subject: Asunto de prueba');
    } else {
        // Fallback básico si no se soporta el array transport
        $res = $this->notificationCore->enviarCorreo(
            'test@sigtram.gob.pe',
            'Asunto de prueba',
            'emails.test',
            ['key' => 'value'],
            false
        );
        expect($res)->toBeArray()->and($res['success'])->toBeTrue();
    }
});
