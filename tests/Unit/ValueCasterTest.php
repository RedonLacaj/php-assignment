<?php

namespace Tests\Unit;

use App\Services\ValueCaster;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ValueCasterTest extends TestCase
{
    #[DataProvider('values')]
    public function test_cast(mixed $value, string $type, mixed $expected): void
    {
        $this->assertSame($expected, (new ValueCaster)->cast($value, $type));
    }

    public static function values(): array
    {
        return [
            'empty string' => ['', 'string', null],
            'trimmed string' => ['  Chair ', 'string', 'Chair'],
            'money' => ['$1,250.50', 'decimal', 1250.5],
            'comma decimal' => ['12,5', 'decimal', 12.5],
            'number' => [42, 'decimal', 42.0],
            'not a number' => ['abc', 'decimal', null],
            'integer rounds' => ['3.6', 'integer', 4],
            'yes' => ['Yes', 'boolean', 1],
            'no' => ['no', 'boolean', 0],
            'date string' => ['2025-01-10', 'date', '2025-01-10'],
            'sheets serial date' => [45667, 'date', '2025-01-10'],
            'datetime' => ['2025-01-10 14:30', 'datetime', '2025-01-10 14:30:00'],
        ];
    }
}
