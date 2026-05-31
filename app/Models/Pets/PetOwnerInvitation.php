<?php

namespace App\Models\Pets;

use App\Enums\Pets\PetOwnerRole;
use App\Models\Identity\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Fillable([
    'pet_id',
    'invited_user_id',
    'inviting_user_id',
    'role',
    'status',
    'expires_at',
    'responded_at',
])]
class PetOwnerInvitation extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_DECLINED = 'declined';

    public const STATUS_EXPIRED = 'expired';

    protected function casts(): array
    {
        return [
            'role' => PetOwnerRole::class,
            'expires_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Pet, $this>
     */
    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function invitedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function invitingUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inviting_user_id');
    }

    /**
     * @param  Builder<PetOwnerInvitation>  $query
     * @return Builder<PetOwnerInvitation>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function isPending(): bool
    {
        return (string) $this->getAttribute('status') === self::STATUS_PENDING
            && ! $this->isExpired();
    }

    public function isExpired(): bool
    {
        return $this->expiresAt()?->isPast() ?? false;
    }

    public function roleValue(): PetOwnerRole
    {
        $role = $this->getAttribute('role');

        if ($role instanceof PetOwnerRole) {
            return $role;
        }

        return PetOwnerRole::tryFrom((string) $role) ?? PetOwnerRole::Viewer;
    }

    private function expiresAt(): ?CarbonInterface
    {
        $expiresAt = $this->getAttribute('expires_at');

        if ($expiresAt instanceof CarbonInterface) {
            return $expiresAt;
        }

        if (! is_string($expiresAt) || $expiresAt === '') {
            return null;
        }

        return Carbon::parse($expiresAt);
    }
}
