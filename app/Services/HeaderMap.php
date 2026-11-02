<?php

namespace App\Services;

/**
 * Positions of columns in a sheet by header name. Matching ignores case and
 * extra spaces, so "Product  Name" and "product name" are the same column.
 */
class HeaderMap
{
    private array $positions = [];

    public function __construct(array $headerRow)
    {
        foreach ($headerRow as $index => $name) {
            $key = self::normalize((string) $name);

            if ($key !== '' && ! isset($this->positions[$key])) {
                $this->positions[$key] = $index;
            }
        }
    }

    public function position(string $header): ?int
    {
        return $this->positions[self::normalize($header)] ?? null;
    }

    private static function normalize(string $name): string
    {
        return mb_strtolower(preg_replace('/\s+/', ' ', trim($name)));
    }
}
