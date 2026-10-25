<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Canales de difusión
|--------------------------------------------------------------------------
|
| Cada persona escucha solo su propio canal privado. En F4 el backend emite
| aquí los cambios de viaje y las alertas del conductor. Se aceptan los guards
| `sanctum` (app móvil, token) y `web` (panel del auditor, sesión).
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
}, ['guards' => ['sanctum', 'web']]);
