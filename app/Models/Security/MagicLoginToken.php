<?php

namespace App\Models\Security;

use App\Models\Identity\User;
use Database\Factories\Security\MagicLoginTokenFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'public_id',
    'user_id',
    'token_hash',
    'ip_address',
    'user_agent',
    'expires_at',
    'consumed_at',
])]
#[Hidden([
    'token_hash',
])]
class MagicLoginToken extends Model
{
    /** @use HasFactory<MagicLoginTokenFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isConsumable(): bool
    {
        return $this->consumed_at === null && now()->lessThan($this->expires_at);
    }
}
