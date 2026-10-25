<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Correo de recuperación de contraseña.
 *
 * El enlace apunta a una página web y no a la app: así funciona igual para el
 * auditor, que entra desde el navegador, y para el pasajero o conductor, que
 * abren el correo desde el teléfono. Después de cambiarla vuelven a la app.
 */
class RestablecerContrasena extends ResetPassword
{
    protected function resetUrl($notifiable): string
    {
        // El correo necesita una URL absoluta: un enlace relativo no se puede
        // abrir desde Gmail. En una petición se usa el host con el que entró la
        // app; fuera de ella manda `APP_URL`.
        return route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $minutos = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);
        $url = $this->resetUrl($notifiable);

        return (new MailMessage)
            ->subject('Restablece tu contraseña de GAZ')
            ->view('emails.auth.recuperar-contrasena', [
                'url' => $url,
                'notifiable' => $notifiable,
                'minutos' => $minutos,
            ])
            ->text('emails.auth.recuperar-contrasena-text', [
                'url' => $url,
                'notifiable' => $notifiable,
                'minutos' => $minutos,
            ]);
    }
}
