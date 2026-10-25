<?php

namespace App\Enums;

enum VehicleType: string
{
    case Motorcycle = 'moto';
    case Car = 'carro';

    public function label(): string
    {
        return match ($this) {
            self::Motorcycle => 'Moto',
            self::Car => 'Carro',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(
            static fn (self $tipo): string => $tipo->value,
            self::cases(),
        );
    }
}
