<?php

namespace Tests\Feature;

use App\Services\MaterialQuantityConverter;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InventoryConversionTest extends TestCase
{
    public static function conversions(): array
    {
        return [
            ['750', 'ml', 'L', '0.750000'], ['1.250', 'L', 'ml', '1250.000000'],
            ['250', 'gr', 'Kg', '0.250000'], ['2.5', 'Kg', 'gr', '2500.000000'],
            ['150', 'cm', 'mts', '1.500000'], ['1.25', 'mts', 'cm', '125.000000'],
            ['3', 'Pza', 'Pza', '3.000000'], ['5.5', 'hojas', 'hojas', '5.500000'],
            ['0.000001', 'L', 'L', '0.000001'], ['999999999999.999999', 'Kg', 'Kg', '999999999999.999999'],
            ['0.001', 'ml', 'L', '0.000001'], ['0.100000', 'Kg', 'Kg', '0.100000'],
        ];
    }

    #[DataProvider('conversions')]
    public function test_exact_conversions(string $quantity, string $from, string $to, string $expected): void
    {
        $this->assertSame($expected, app(MaterialQuantityConverter::class)->convert($quantity, $from, $to));
    }

    public static function invalid(): array
    {
        return [['1', 'L', 'gr'], ['1', 'Pza', 'hojas'], ['0', 'Kg', 'Kg'], ['-1', 'Kg', 'Kg'], ['1e3', 'Kg', 'Kg'], ['0.000001', 'ml', 'L'], ['1000000000000', 'Kg', 'Kg'], ['1.1234567', 'Kg', 'Kg'], ['1000000000', 'L', 'ml']];
    }

    #[DataProvider('invalid')]
    public function test_incompatible_or_unrepresentable_quantities_are_rejected(string $quantity, string $from, string $to): void
    {
        $this->expectException(ValidationException::class);
        app(MaterialQuantityConverter::class)->convert($quantity, $from, $to);
    }
}
