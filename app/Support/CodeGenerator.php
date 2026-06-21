<?php

namespace App\Support;

class CodeGenerator
{
    public static function next(string $modelClass, string $column, string $prefix, int $padding = 5): string
    {
        $nextId = ((int) $modelClass::query()->max('id')) + 1;

        do {
            $code = $prefix.'-'.str_pad((string) $nextId, $padding, '0', STR_PAD_LEFT);
            $exists = $modelClass::query()->where($column, $code)->exists();
            $nextId++;
        } while ($exists);

        return $code;
    }
}
