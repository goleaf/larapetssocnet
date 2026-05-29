<?php

namespace App\Models\Identity;

use Database\Factories\Identity\SocialAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[UseFactory(SocialAccountFactory::class)]
#[Fillable([
    'user_id',
    'provider',
    'provider_user_id',
    'provider_avatar_url',
    'provider_token',
    'provider_token_expires_at',
])]
#[Hidden([
    'provider_token',
])]
class SocialAccount extends Model
{
    /** @use HasFactory<SocialAccountFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'provider_token' => 'encrypted',
            'provider_token_expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
