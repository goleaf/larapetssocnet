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
    'provider_id',
    'provider_email',
    'provider_nickname',
    'provider_name',
    'avatar_url',
    'token',
    'refresh_token',
    'expires_at',
    'provider_payload',
])]
#[Hidden([
    'token',
    'refresh_token',
])]
class SocialAccount extends Model
{
    /** @use HasFactory<SocialAccountFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'expires_at' => 'datetime',
            'provider_payload' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
