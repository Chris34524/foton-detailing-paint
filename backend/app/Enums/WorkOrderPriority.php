<?php

namespace App\Enums;

use Illuminate\Database\Eloquent\Builder;

enum WorkOrderPriority: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Baja', self::Medium => 'Media', self::High => 'Alta', self::Critical => 'Crítica',
        };
    }

    public function rank(): int
    {
        return match ($this) {
            self::Low => 1, self::Medium => 2, self::High => 3, self::Critical => 4,
        };
    }

    /** @param iterable<DefectPriority> $priorities */
    public static function highest(iterable $priorities): ?self
    {
        $highest = null;
        foreach ($priorities as $priority) {
            $candidate = self::from($priority->value);
            if ($highest === null || $candidate->rank() > $highest->rank()) {
                $highest = $candidate;
            }
        }

        return $highest;
    }

    public static function order(Builder $query, string $direction): void
    {
        $parts = [];
        $bindings = [];
        foreach (self::cases() as $priority) {
            $parts[] = 'WHEN ? THEN ?';
            array_push($bindings, $priority->value, $priority->rank());
        }
        $query->orderByRaw('CASE priority '.implode(' ', $parts).' ELSE 0 END '.($direction === 'asc' ? 'ASC' : 'DESC'), $bindings);
    }
}
