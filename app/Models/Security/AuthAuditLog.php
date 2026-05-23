<?php

namespace App\Models\Security;

use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'event_type',
    'ip_address',
    'user_agent',
    'country',
    'city',
    'additional_data',
])]
class AuthAuditLog extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'additional_data' => 'array',
        ];
    }

    protected function metadata(): Attribute
    {
        return Attribute::get(fn (): ?array => $this->additional_data);
    }

    protected function identifierHash(): Attribute
    {
        return Attribute::get(function (): ?string {
            $identifierHash = $this->additional_data['identifier_hash'] ?? null;

            return is_string($identifierHash) ? $identifierHash : null;
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
