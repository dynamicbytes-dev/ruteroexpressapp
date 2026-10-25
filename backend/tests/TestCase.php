<?php

namespace Tests;

use App\Support\Configuracion;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Las pruebas no necesitan el bundle de Vite; así también corren en CI
        // sin tener que compilar Tailwind/JS antes de cada suite.
        $this->withoutVite();

        // La configuración operativa se cachea en memoria durante la petición;
        // entre pruebas hay que empezar limpio.
        Configuracion::flush();
    }
}
