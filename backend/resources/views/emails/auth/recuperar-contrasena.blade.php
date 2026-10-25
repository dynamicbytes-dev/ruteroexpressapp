<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restablece tu contraseña de GAZ</title>
    <style>
        body, table, td, p, a, li, blockquote {
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }
        body {
            margin: 0 !important;
            padding: 0 !important;
            background-color: #0c0214;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            color: #2d3748;
        }
        .main-card {
            background-color: #ffffff;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.35);
        }
        .header-bg {
            background: linear-gradient(135deg, #1b0730 0%, #310b54 50%, #150524 100%);
            padding: 40px 32px 30px 32px;
            text-align: center;
        }
        .logo-gaz {
            font-size: 38px;
            font-weight: 900;
            letter-spacing: -1.5px;
            color: #ffffff;
            text-transform: lowercase;
            margin: 0;
            display: inline-block;
        }
        .logo-gaz span {
            color: #00f0ff;
        }
        .badge-security {
            display: inline-block;
            background-color: rgba(0, 240, 255, 0.15);
            border: 1px solid rgba(0, 240, 255, 0.4);
            color: #00f0ff;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            padding: 4px 12px;
            border-radius: 20px;
            margin-top: 12px;
        }
        .content-body {
            padding: 36px 32px;
        }
        .btn-reset {
            display: inline-block;
            background: linear-gradient(135deg, #6c11b0 0%, #9a1ee0 100%);
            color: #ffffff !important;
            text-decoration: none;
            padding: 16px 36px;
            border-radius: 14px;
            font-size: 15px;
            font-weight: 700;
            letter-spacing: 0.2px;
            box-shadow: 0 6px 18px rgba(108, 17, 176, 0.35);
            text-align: center;
        }
        .notice-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-left: 4px solid #00c8e0;
            border-radius: 10px;
            padding: 16px 18px;
            margin-top: 28px;
        }
        .footer-text {
            color: #718096;
            font-size: 12px;
            text-align: center;
            padding: 24px 16px;
            line-height: 1.6;
        }
    </style>
</head>
<body style="margin: 0; padding: 30px 15px; background-color: #0c0214;">
    <table border="0" cellpadding="0" cellspacing="0" width="100%" style="table-layout: fixed;">
        <tr>
            <td align="center">
                <table border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 560px;" class="main-card">
                    {{-- Encabezado con identidad GAZ --}}
                    <tr>
                        <td class="header-bg">
                            <h1 class="logo-gaz">g<span>a</span>z</h1>
                            <br>
                            <span class="badge-security">Seguridad de la cuenta</span>
                        </td>
                    </tr>

                    {{-- Cuerpo del correo --}}
                    <tr>
                        <td class="content-body">
                            <h2 style="font-size: 22px; font-weight: 700; color: #1a202c; margin: 0 0 14px 0; line-height: 1.3;">
                                Hola, {{ $notifiable->first_name ?? 'Usuario' }} 👋
                            </h2>

                            <p style="font-size: 15px; line-height: 1.65; color: #4a5568; margin: 0 0 20px 0;">
                                Recibimos una solicitud para restablecer la contraseña de tu cuenta en <strong>GAZ</strong>. Si fuiste tú, puedes crear una contraseña nueva haciendo clic en el siguiente botón:
                            </p>

                            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin: 28px 0;">
                                <tr>
                                    <td align="center">
                                        <a href="{{ $url }}" class="btn-reset" target="_blank">
                                            Restablecer mi contraseña
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <div class="notice-box">
                                <p style="font-size: 13px; font-weight: 600; color: #2d3748; margin: 0 0 4px 0;">
                                    Información de seguridad importante:
                                </p>
                                <ul style="font-size: 12.5px; color: #4a5568; margin: 0; padding-left: 18px; line-height: 1.55;">
                                    <li>Este enlace vence en <strong>{{ $minutos ?? 60 }} minutos</strong> y solo puede utilizarse una vez.</li>
                                    <li>Si no solicitaste este cambio, puedes ignorar este correo con tranquilidad: tu contraseña actual continúa siendo segura.</li>
                                </ul>
                            </div>

                            <p style="font-size: 12px; color: #a0aec0; margin: 26px 0 0 0; line-height: 1.5; word-break: break-all;">
                                Si el botón no funciona, copia y pega el siguiente enlace en tu navegador:<br>
                                <a href="{{ $url }}" style="color: #6c11b0; text-decoration: underline;">{{ $url }}</a>
                            </p>
                        </td>
                    </tr>

                    {{-- Pie de página --}}
                    <tr>
                        <td style="background-color: #f1f5f9; padding: 20px 32px; border-top: 1px solid #e2e8f0; text-align: center;">
                            <p style="font-size: 12px; color: #64748b; margin: 0; line-height: 1.5;">
                                <strong>GAZ</strong> · Movilidad ágil, transparente y segura.<br>
                                Este es un mensaje automático, por favor no respondas directamente a este correo.
                            </p>
                        </td>
                    </tr>
                </table>

                <table border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 560px;">
                    <tr>
                        <td class="footer-text">
                            © {{ date('Y') }} GAZ. Todos los derechos reservados.<br>
                            Protegiendo tu cuenta y tus viajes en cada trayecto.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
