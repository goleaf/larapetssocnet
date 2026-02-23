<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class UserBadge extends Pivot
{
    public const CREATED_AT = null;

    public const UPDATED_AT = null;

    protected $table = 'user_badges';

    public $incrementing = false;

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'badge_id',
        'awarded_at',
        'awarded_by',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'awarded_at' => 'datetime',
            'awarded_by' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function badge(): BelongsTo
    {
        return $this->belongsTo(Badge::class);
    }

    public function awarder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'awarded_by');
    }
}
