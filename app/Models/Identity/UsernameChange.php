<?php

namespace App\Models\Identity;

use Database\Factories\UsernameChangeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[UseFactory(UsernameChangeFactory::class)]
#[Fillable([
    'user_id',
    'old_username',
    'new_username',
    'changed_by',
    'reason',
    'changed_at',
    'ip_address',
    'user_agent',
])]
class UsernameChange extends Model
{
    /** @use HasFactory<UsernameChangeFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'changed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
