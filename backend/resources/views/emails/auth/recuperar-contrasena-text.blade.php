Hola {{ $notifiable->first_name ?? 'Usuario' }},

Recibimos una solicitud para restablecer la contraseña de tu cuenta de GAZ.

Para crear una contraseña nueva, abre el siguiente enlace en tu navegador:
{{ $url }}

Este enlace vence en {{ $minutos ?? 60 }} minutos y solo se puede usar una vez.

Si no fuiste tú, puedes ignorar este correo: tu contraseña actual sigue funcionando.

Equipo GAZ
Movilidad ágil, transparente y segura.
