<?php

namespace App\Enums;

enum DocumentType: string
{
    case NationalId = 'cedula';
    case Selfie = 'selfie';
    case DriversLicense = 'licencia_conducir';
    case VehicleRegistration = 'carnet_circulacion';
    case VehiclePhoto = 'foto_vehiculo';
    case Rcv = 'rcv';

    public function label(): string
    {
        return match ($this) {
            self::NationalId => 'Foto de la cédula',
            self::Selfie => 'Foto selfie',
            self::DriversLicense => 'Licencia de conducir',
            self::VehicleRegistration => 'Carnet de circulación',
            self::VehiclePhoto => 'Foto del vehículo',
            self::Rcv => 'RCV (seguro del vehículo)',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::NationalId => 'Ambas caras de la cédula, nítidas y sin reflejos.',
            self::Selfie => 'Rostro descubierto, con buena luz y sin lentes.',
            self::DriversLicense => 'Licencia vigente, se debe leer la fecha de vencimiento.',
            self::VehicleRegistration => 'Carnet de circulación a nombre del conductor titular.',
            self::VehiclePhoto => 'Foto completa del vehículo, con la placa visible.',
            self::Rcv => 'Póliza de responsabilidad civil vigente.',
        };
    }

    /** El documento tiene fecha de vencimiento y debe vigilarse. */
    public function expires(): bool
    {
        return in_array($this, [
            self::DriversLicense,
            self::VehicleRegistration,
            self::Rcv,
        ], true);
    }

    /** Tipos de imagen aceptados al subir el documento. */
    public static function acceptedMimeTypes(): string
    {
        return 'jpg,jpeg,png,webp,pdf';
    }

    /** Tamaño máximo del archivo en kilobytes. */
    public static function maxSizeKb(): int
    {
        return 6144; // 6 MB
    }
}
