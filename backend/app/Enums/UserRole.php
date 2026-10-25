<?php

namespace App\Enums;

enum UserRole: string
{
    case Passenger = 'passenger';
    case Driver = 'driver';
    case Auditor = 'auditor';

    public function label(): string
    {
        return match ($this) {
            self::Passenger => 'Pasajero',
            self::Driver => 'Conductor',
            self::Auditor => 'Auditor',
        };
    }

    /**
     * Roles que puede elegir una persona al registrarse desde la app.
     * El auditor nunca se registra solo, lo crea un administrador.
     *
     * @return array<int, self>
     */
    public static function selfRegistrable(): array
    {
        return [self::Passenger, self::Driver];
    }

    /**
     * Documentos que debe subir cada rol, en orden de presentación.
     *
     * @return array<int, DocumentType>
     */
    public function requiredDocuments(): array
    {
        return match ($this) {
            self::Passenger => [
                DocumentType::NationalId,
                DocumentType::Selfie,
            ],
            self::Driver => [
                DocumentType::NationalId,
                DocumentType::Selfie,
                DocumentType::DriversLicense,
                DocumentType::VehicleRegistration,
                DocumentType::Rcv,
                DocumentType::VehiclePhoto,
            ],
            self::Auditor => [],
        };
    }
}
