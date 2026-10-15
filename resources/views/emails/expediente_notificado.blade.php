<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Expediente Notificado</title>
<style>body { color:#163b59; } h2 { color:#126bbb; }</style>
</head>
<body style="font-family: Arial, sans-serif; background-color: #f3f4f6; padding: 20px; color: #1f2937; margin: 0;">
@include('components.sigtram.mail-brand')
    <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; padding: 30px; border: 1px solid #e5e7eb;">
        <div style="text-align: center; border-bottom: 2px solid #e5e7eb; padding-bottom: 20px; margin-bottom: 20px;">
            <h2 style="color: #2563eb; margin: 0; text-transform: uppercase; font-size: 18px;">Notificación de expediente</h2>
            <p style="font-weight: bold; color: #4b5563; font-size: 16px; margin: 8px 0 0 0;">Expediente {{ $expediente->nu_expe_todo }}</p>
        </div>

        <div style="font-size: 14px; line-height: 1.6;">
            <p><strong>Senor(es):</strong></p>
            <p style="font-weight: bold; color: #111827; font-size: 15px; margin-top: 5px;">{{ $expediente->no_crto }}</p>
            <p>Se registra la notificacion de su expediente en la oficina: <strong style="color: #2563eb;">{{ $area }}</strong>.</p>
            <p><strong>Observaciones:</strong></p>
            <div style="background-color: #f9fafb; border-left: 4px solid #3b82f6; padding: 15px; border-radius: 4px; color: #374151; font-size: 13px;">
                {{ $observaciones }}
            </div>
        </div>

        <div style="text-align: center; font-size: 11px; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 20px; margin-top: 30px; line-height: 1.4;">
            <p>Correo generado automaticamente por la SIGTRAM · Sistema de Gestión de Trámites.</p>
            <p>Por favor, no responda directamente a este correo.</p>
        </div>
    </div>
</body>
</html>
