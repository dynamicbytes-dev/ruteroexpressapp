<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Aviso al conductor cuando el auditor inhabilita su cuenta.
 *
 * Queda en la bandeja de la app para que sepa por qué no puede conectarse.
 */
class CuentaInhabilitada extends Notification
{
    public function __construct(public readonly string $motivo) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'cuenta_inhabilitada',
            'title' => 'Tu cuenta fue inhabilitada',
            'message' => "Un auditor inhabilitó tu cuenta: {$this->motivo}",
        ];
    }
}
