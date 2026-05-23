<?php

namespace App\Models\Pets;

use App\Models\Identity\User;
use Database\Factories\Pets\PetOwnerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[UseFactory(PetOwnerFactory::class)]
#[Fillable([
    'pet_id',
    'user_id',
    'invited_by_user_id',
    'role',
    'can_post',
    'can_edit',
    'can_manage_health',
    'can_manage_gallery',
    'can_manage_adoption',
    'can_delete',
    'accepted_at',
])]
class PetOwner extends Model
{
    /** @use HasFactory<PetOwnerFactory> */
    use HasFactory;

    public const ROLE_OWNER = 'owner';

    public const ROLE_CO_OWNER = 'co_owner';

    protected function casts(): array
    {
        return [
            'can_post' => 'boolean',
            'can_edit' => 'boolean',
            'can_manage_health' => 'boolean',
            'can_manage_gallery' => 'boolean',
            'can_manage_adoption' => 'boolean',
            'can_delete' => 'boolean',
            'accepted_at' => 'datetime',
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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }
}
