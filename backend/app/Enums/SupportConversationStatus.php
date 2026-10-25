<?php

namespace App\Enums;

enum SupportConversationStatus: string
{
    case Open = 'open';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Abierta',
            self::Closed => 'Finalizada',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Open => 'cyan',
            self::Closed => 'slate',
        };
    }
}
