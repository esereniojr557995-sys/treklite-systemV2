<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

/** Finds a brand / category / size / color by name (ignoring capitals), or creates it. Empty name = none (null). */
class Lookup
{
    /** @param class-string<Model> $model */
    public static function id(string $model, ?string $name): ?int
    {
        $name = trim((string) $name);

        if ($name === '') {
            return null;
        }

        $found = $model::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();

        return ($found ?? $model::create(['name' => $name]))->id;
    }
}
