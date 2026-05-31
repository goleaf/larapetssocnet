<?php

namespace App\Models\Pets;

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
    'current_owner_user_id',
    'proposed_owner_user_id',
    'status',
    'expires_at',
])]
class PetOwnershipTransfer extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_DECLINED = 'declined';

    public const STATUS_CANCELLED = 'cancelled';

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
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
    public function currentOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_owner_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function proposedOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposed_owner_user_id');
    }

    /**
     * @param  Builder<PetOwnershipTransfer>  $query
     * @return Builder<PetOwnershipTransfer>
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
