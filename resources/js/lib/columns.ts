import type { ColumnType } from '@/types';

export const COLUMN_TYPES: ColumnType[] = ['string', 'text', 'integer', 'decimal', 'boolean', 'date', 'datetime'];

export function snakeCase(value: string): string {
    return value
        .trim()
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '_')
        .replace(/^_+|_+$/g, '')
        .replace(/^(\d)/, 'col_$1')
        .slice(0, 64);
}

// Rough guess from sample values, the user can always change it
export function guessType(values: unknown[]): ColumnType {
    const filled = values.filter((v) => v !== null && v !== '');

    if (filled.length === 0) {
        return 'string';
    }

    if (filled.every((v) => typeof v === 'boolean')) {
        return 'boolean';
    }

    if (filled.every((v) => typeof v === 'number' || /^-?[\d\s,]*\.?\d+$/.test(String(v).trim()))) {
        return filled.every((v) => Number.isInteger(Number(String(v).replace(/[\s,]/g, '')))) ? 'integer' : 'decimal';
    }

    if (filled.every((v) => /^\d{4}-\d{2}-\d{2}$|^\d{1,2}[./]\d{1,2}[./]\d{2,4}$/.test(String(v).trim()))) {
        return 'date';
    }

    if (filled.every((v) => !Number.isNaN(Date.parse(String(v))) && /\d{1,2}:\d{2}/.test(String(v)))) {
        return 'datetime';
    }

    return filled.some((v) => String(v).length > 255) ? 'text' : 'string';
}
