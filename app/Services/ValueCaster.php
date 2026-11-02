<?php

namespace App\Services;

use Carbon\Carbon;
use Throwable;

class ValueCaster
{
    public function cast(mixed $value, string $type): mixed
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return null;
        }

        return match ($type) {
            'integer' => $this->toNumber($value) === null ? null : (int) round($this->toNumber($value)),
            'decimal' => $this->toNumber($value),
            'boolean' => $this->toBoolean($value),
            'date' => $this->toDate($value)?->format('Y-m-d'),
            'datetime' => $this->toDate($value)?->format('Y-m-d H:i:s'),
            'string' => mb_substr(trim((string) $value), 0, 255),
            default => trim((string) $value),
        };
    }

    private function toNumber(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        // "$1,250.50", "1 250,5", "12%"
        $clean = preg_replace('/[^\d,.\-]/', '', (string) $value);

        if (str_contains($clean, ',') && ! str_contains($clean, '.')) {
            $clean = str_replace(',', '.', $clean);
        } else {
            $clean = str_replace(',', '', $clean);
        }

        return is_numeric($clean) ? (float) $clean : null;
    }

    private function toBoolean(mixed $value): int
    {
        if (is_bool($value)) {
            return (int) $value;
        }

        return (int) in_array(mb_strtolower(trim((string) $value)), ['1', 'true', 'yes', 'y', 'x', '+'], true);
    }

    private function toDate(mixed $value): ?Carbon
    {
        // Sheets serial number: days since 1899-12-30
        if (is_int($value) || is_float($value)) {
            return Carbon::create(1899, 12, 30)->addSeconds((int) round($value * 86400));
        }

        try {
            return Carbon::parse(trim((string) $value));
        } catch (Throwable) {
            return null;
        }
    }
}
