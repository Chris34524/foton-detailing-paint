<?php

namespace App\Enums;

enum InspectionResult: string
{
    case Passed = 'passed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Passed => 'Cumple',
            self::Failed => 'No cumple',
        };
    }
}
