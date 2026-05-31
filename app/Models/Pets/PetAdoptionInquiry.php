<?php

namespace App\Models\Pets;

use App\Models\Identity\User;
use App\Models\Marketplace\MarketplaceListing;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'pet_id',
    'marketplace_listing_id',
    'user_id',
    'applicant_name',
    'city',
    'country',
    'living_situation',
    'species_experience',
    'other_pets',
    'message',
    'preferred_contact_method',
    'contact_details',
    'status',
])]
class PetAdoptionInquiry extends Model
{
    use HasFactory;

    public const STATUS_SENT = 'sent';

    /**
     * @return BelongsTo<Pet, $this>
     */
    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }

    /**
     * @return BelongsTo<MarketplaceListing, $this>
     */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(MarketplaceListing::class, 'marketplace_listing_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
