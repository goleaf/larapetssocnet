<?php

namespace App\Models\Security;

use App\Models\Identity\User;
use Database\Factories\Security\LoginSecurityAlertFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'token_hash',
    'country_code',
    'country',
    'city',
    'ip_address',
    'user_agent',
    'device_type',
    'browser_name',
    'browser_version',
    'os_name',
    'os_version',
    'login_at',
    'dismissed_at',
    'secured_at',
])]
#[Hidden([
    'token_hash',
])]
class LoginSecurityAlert extends Model
{
    /** @use HasFactory<LoginSecurityAlertFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'login_at' => 'datetime',
            'dismissed_at' => 'datetime',
            'secured_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isConsumed(): bool
    {
        return $this->dismissed_at !== null || $this->secured_at !== null;
    }
}
