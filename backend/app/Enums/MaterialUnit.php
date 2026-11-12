<?php

namespace App\Enums;

enum MaterialUnit: string
{
    case Kilogram = 'Kg';
    case Litre = 'L';
    case Millilitre = 'ml';
    case Piece = 'Pza';
    case Gram = 'gr';
    case Centimetre = 'cm';
    case Metre = 'mts';
    case Sheet = 'hojas';

    public function family(): string
    {
        return match ($this) {
            self::Kilogram,self::Gram => 'mass',
            self::Litre,self::Millilitre => 'volume',
            self::Metre,self::Centimetre => 'length',
            self::Piece => 'pieces', self::Sheet => 'sheets',
        };
    }

    public function factor(): string
    {
        return match ($this) {
            self::Kilogram,self::Litre => '1000', self::Metre => '100', default => '1'
        };
    }

    public static function normalize(string $value): ?self
    {
        return match (mb_strtolower(trim($value))) {
            'kg' => self::Kilogram,'l' => self::Litre,'ml' => self::Millilitre,'pza' => self::Piece,
            'g','gr' => self::Gram,'cm' => self::Centimetre,'m','mts' => self::Metre,'hoja','hojas' => self::Sheet,
            default => null,
        };
    }
}
