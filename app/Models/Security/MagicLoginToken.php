<?php

namespace App\Models\Security;

use App\Models\Identity\User;
use Database\Factories\Security\MagicLoginTokenFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'token',
    'token_hash',
    'expires_at',
    'used_at',
])]
#[Hidden([
    'token_hash',
])]
#[Table(name: 'magic_link_tokens')]
class MagicLoginToken extends Model
{
    /** @use HasFactory<MagicLoginTokenFactory> */
    use HasFactory;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
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
        return $this->used_at === null && now()->lessThan($this->expires_at);
    }
}
