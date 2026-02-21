<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReservedUsername extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'username',
        'reason',
        'created_at',
    ];

    public static function isReserved(string $username): bool
    {
        return static::query()
            ->where('username', strtolower($username))
            ->exists();
    }
}

