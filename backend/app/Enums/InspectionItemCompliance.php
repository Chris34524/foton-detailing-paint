<?php

namespace App\Enums;

enum InspectionItemCompliance: string
{
    case Compliant = 'compliant';
    case NonCompliant = 'non_compliant';

    public function label(): string
    {
        return match ($this) {
            self::Compliant => 'Cumple',
            self::NonCompliant => 'No cumple',
        };
    }
}
