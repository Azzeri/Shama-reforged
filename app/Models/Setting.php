<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Household-wide key → JSON value settings. */
#[Fillable(['key', 'value'])]
class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return ['value' => 'array'];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::query()->find($key)?->value ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        $value === null
            ? self::query()->whereKey($key)->delete()
            : self::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
