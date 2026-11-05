<?php

namespace App\Enums;

enum InspectionStatus: string
{
    case Draft = 'draft';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::Completed => 'Completada',
        };
    }
}
