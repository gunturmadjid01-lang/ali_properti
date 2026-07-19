<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

final class SchemaMetadata
{
    /** @var array<string, bool> */
    private static array $tables = [];

    /** @var array<string, bool> */
    private static array $columns = [];

    public static function hasTable(string $table): bool
    {
        return self::$tables[$table] ??= Schema::hasTable($table);
    }

    public static function hasColumn(string $table, string $column): bool
    {
        $key = $table.'.'.$column;

        return self::$columns[$key] ??= self::hasTable($table) && Schema::hasColumn($table, $column);
    }

    public static function flush(): void
    {
        self::$tables = [];
        self::$columns = [];
    }
}
