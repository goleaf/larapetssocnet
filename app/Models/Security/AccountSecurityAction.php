<?php

namespace App\Models\Security;

use App\Models\Identity\User;
use Carbon\CarbonImmutable;
use Database\Factories\Security\AccountSecurityActionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'action_type',
    'token_hash',
    'used_at',
    'expires_at',
])]
class AccountSecurityAction extends Model
{
    /** @use HasFactory<AccountSecurityActionFactory> */
    use HasFactory;

    public const ACTION_PASSWORD_RESET_EMERGENCY_LOCK = 'password_reset_emergency_lock';

    protected function casts(): array
    {
        return [
            'used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isUsed(): bool
    {
        return $this->getAttribute('used_at') !== null;
    }

    public function isExpired(): bool
    {
        $expiresAt = $this->getAttribute('expires_at');

        if ($expiresAt === null) {
            return false;
        }

        return CarbonImmutable::parse($expiresAt)->isPast();
    }
}
