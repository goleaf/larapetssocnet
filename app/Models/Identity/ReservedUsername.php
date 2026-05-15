<?php

namespace App\Models\Identity;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'username',
    'reason',
    'created_at',
])]
class ReservedUsername extends Model
{
    public $timestamps = false;

    public static function isReserved(string $username): bool
    {
        return static::query()
            ->where('username', strtolower($username))
            ->exists();
    }
}
