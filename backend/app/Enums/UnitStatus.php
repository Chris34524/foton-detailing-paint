<?php

namespace App\Enums;

enum UnitStatus: string
{
    case Registered = 'registered';
    case InDiagnosis = 'in_diagnosis';
    case InProcess = 'in_process';
    case Inspection = 'inspection';
    case Rework = 'rework';
    case ReadyForRelease = 'ready_for_release';
    case Released = 'released';
    case Blocked = 'blocked';

    public function label(): string
    {
        return match ($this) {
            self::Registered => 'Registrada',
            self::InDiagnosis => 'En diagnóstico',
            self::InProcess => 'En proceso',
            self::Inspection => 'En inspección',
            self::Rework => 'Retrabajo',
            self::ReadyForRelease => 'Lista para liberar',
            self::Released => 'Liberada',
            self::Blocked => 'Bloqueada',
        };
    }
}
