<?php

namespace Tests\Unit;

use App\Rules\GmailOnlyEmail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Regla del negocio: solo se aceptan correos de Gmail.
 * Hotmail y cualquier otro proveedor quedan fuera.
 */
class GmailOnlyEmailTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function correosPermitidos(): array
    {
        return [
            'gmail normal' => ['juan@gmail.com'],
            'gmail con puntos' => ['juan.perez@gmail.com'],
            'gmail con mas' => ['juan+bici@gmail.com'],
            'gmail en mayusculas' => ['JUAN@GMAIL.COM'],
            'gmail con espacios' => ['  juan@gmail.com  '],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function correosBloqueados(): array
    {
        return [
            'hotmail' => ['juan@hotmail.com'],
            'hotmail espana' => ['juan@hotmail.es'],
            'outlook' => ['juan@outlook.com'],
            'live' => ['juan@live.com'],
            'yahoo' => ['juan@yahoo.com'],
            'corporativo' => ['juan@empresa.com.ve'],
            'dominio parecido a gmail' => ['juan@gmail.com.evil.io'],
            'subdominio de gmail' => ['juan@correo.gmail.com'],
            'gmail sin arroba' => ['juan.gmail.com'],
            'vacio' => [''],
        ];
    }

    #[DataProvider('correosPermitidos')]
    public function test_acepta_gmail(string $correo): void
    {
        $this->assertTrue(GmailOnlyEmail::isAllowed($correo), "Debería aceptar {$correo}");
    }

    #[DataProvider('correosBloqueados')]
    public function test_bloquea_otros_proveedores(string $correo): void
    {
        $this->assertFalse(GmailOnlyEmail::isAllowed($correo), "Debería bloquear {$correo}");
    }
}
