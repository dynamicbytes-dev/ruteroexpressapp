<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Layout de las páginas públicas: acceso al panel y las páginas que abren los
 * correos (verificación y recuperación de contraseña).
 *
 * `contexto` cambia el costado de marca: `panel` para el auditor y `app` para
 * quien llega desde el teléfono.
 */
class GuestLayout extends Component
{
    public function __construct(
        public string $title = 'Acceso',
        public string $contexto = 'panel',
    ) {}

    public function render(): View
    {
        return view('layouts.guest');
    }
}
