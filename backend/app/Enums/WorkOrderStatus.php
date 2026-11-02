<?php

namespace App\Enums;

enum WorkOrderStatus: string
{
    case Queued = 'queued';
    case InProgress = 'in_progress';
    case Blocked = 'blocked';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'En cola', self::InProgress => 'En proceso', self::Blocked => 'Bloqueada',
            self::Completed => 'Completada', self::Cancelled => 'Cancelada',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled], true);
    }

    /** @return list<self> */
    public function transitions(): array
    {
        return match ($this) {
            self::Queued => [self::InProgress, self::Blocked, self::Cancelled],
            self::InProgress => [self::Blocked, self::Completed, self::Cancelled],
            self::Blocked => [self::Queued, self::InProgress, self::Cancelled],
            self::Completed, self::Cancelled => [],
        };
    }
}
