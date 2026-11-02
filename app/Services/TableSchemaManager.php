<?php

namespace App\Services;

use App\Models\TableMapping;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class TableSchemaManager
{
    /**
     * Creates the target table if needed and adds columns that are in the
     * mapping but not in the table yet. Returns the names of added columns.
     */
    public function sync(TableMapping $mapping): array
    {
        $table = $mapping->target_table;
        $this->assertIdentifier($table);

        if (in_array($table, config('sheets.protected_tables'), true)) {
            throw new InvalidArgumentException("Table [{$table}] is protected and can't be used as a target.");
        }

        if (! Schema::hasTable($table)) {
            Schema::create($table, function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('table_mapping_id')->index();
                $t->timestamp('synced_at')->nullable()->index();
            });
        }

        $existing = array_map('strtolower', Schema::getColumnListing($table));
        $missing = [];

        foreach (['table_mapping_id', 'synced_at'] as $meta) {
            if (! in_array($meta, $existing, true)) {
                $missing[$meta] = $meta === 'synced_at' ? 'datetime' : 'meta_id';
            }
        }

        foreach ($mapping->columns as $column) {
            $this->assertIdentifier($column->target_column);

            if (! in_array(strtolower($column->target_column), $existing, true)) {
                $missing[$column->target_column] = $column->type;
            }
        }

        if ($missing) {
            Schema::table($table, function (Blueprint $t) use ($missing) {
                foreach ($missing as $name => $type) {
                    $this->addColumn($t, $name, $type);
                }
            });
        }

        return array_keys($missing);
    }

    private function addColumn(Blueprint $t, string $name, string $type): void
    {
        $column = match ($type) {
            'meta_id' => $t->unsignedBigInteger($name),
            'text' => $t->text($name),
            'integer' => $t->bigInteger($name),
            'decimal' => $t->decimal($name, 15, 4),
            'boolean' => $t->boolean($name),
            'date' => $t->date($name),
            'datetime' => $t->dateTime($name),
            default => $t->string($name),
        };

        $column->nullable();
    }

    private function assertIdentifier(string $name): void
    {
        if (! preg_match('/^[a-z][a-z0-9_]{0,63}$/', $name)) {
            throw new InvalidArgumentException("Invalid table or column name [{$name}].");
        }
    }
}
