<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsernameRedirect extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'old_username',
        'user_id',
        'redirects_until',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'redirects_until' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('redirects_until', '>=', now());
    }
}
