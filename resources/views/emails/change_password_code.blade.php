<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Código de Verificación - SIGTRAM</title>
<style>body { color:#163b59; } h2 { color:#126bbb; }</style>
</head>
<body style="font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f3f4f6; padding: 30px; color: #1f2937; margin: 0;">
@include('components.sigtram.mail-brand')
    <div style="max-width: 550px; margin: 0 auto; background-color: #ffffff; border-radius: 16px; padding: 40px; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05); border: 1px solid #e5e7eb;">
        
        <!-- Header -->
        <div style="text-align: center; border-bottom: 2px solid #f3f4f6; padding-bottom: 25px; margin-bottom: 25px;">
            <div style="display: inline-block; padding: 12px; background-color: #dbeafe; border-radius: 12px; margin-bottom: 12px;">
                <span style="font-size: 28px;">🔑</span>
            </div>
            <h2 style="color: #1e3a8a; margin: 0; text-transform: uppercase; font-size: 20px; font-weight: 800; letter-spacing: 0.05em; line-height: 1.2;">
                SIGTRAM · Sistema de Gestión de Trámites
            </h2>
            <p style="font-weight: 700; color: #3b82f6; font-size: 13px; margin: 6px 0 0 0; text-transform: uppercase; letter-spacing: 0.1em;">
                Sistema de Gestión de Trámite Municipal — SIGTRAM
            </p>
        </div>
        
        <!-- Content -->
        <div style="margin-bottom: 25px; font-size: 15px; line-height: 1.6; color: #4b5563;">
            <p>Hola, <strong>{{ $username }}</strong>,</p>
            <p>Hemos recibido una solicitud para cambiar la contraseña de tu cuenta de acceso a la plataforma SIGTRAM.</p>
            
            <p style="margin-top: 20px;">Por seguridad, utiliza el siguiente código de verificación temporal para confirmar los cambios:</p>
            
            <!-- Code Box -->
            <div style="text-align: center; margin: 30px 0; padding: 20px; background-color: #eff6ff; border: 2px dashed #bfdbfe; border-radius: 14px;">
                <span style="font-size: 36px; font-weight: 900; letter-spacing: 10px; color: #1d4ed8; font-family: 'Courier New', Courier, monospace; display: block; margin-left: 10px;">
                    {{ $code }}
                </span>
            </div>
            
            <p style="font-size: 13px; color: #ef4444; font-weight: bold; background-color: #fef2f2; padding: 12px; border-radius: 8px; border-left: 4px solid #ef4444; margin-top: 20px;">
                Este código expira en 10 minutos por razones de seguridad. No compartas este código con nadie.
            </p>
            
            <p style="margin-top: 25px; font-size: 13px; font-style: italic;">
                Si tú no solicitaste este cambio, puedes ignorar este correo de forma segura. Tu contraseña actual no sufrirá modificaciones.
            </p>
        </div>

        <!-- Footer -->
        <div style="text-align: center; font-size: 11px; color: #9ca3af; border-top: 1px solid #f3f4f6; padding-top: 25px; margin-top: 35px; line-height: 1.5;">
            <p>Este es un correo de notificación generado automáticamente por la plataforma SIGTRAM.</p>
            <p>Por favor, no respondas directamente a este mensaje.</p>
            <p style="font-weight: bold; margin-top: 12px; color: #6b7280;">&copy; {{ date('Y') }} SIGTRAM · Sistema de Gestión de Trámites</p>
        </div>
        
    </div>
</body>
</html>
