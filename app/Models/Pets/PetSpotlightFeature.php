<?php

namespace App\Models\Pets;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'pet_id',
    'featured_week_start',
    'engagement_rate',
    'selected_at',
    'expires_at',
])]
class PetSpotlightFeature extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'featured_week_start' => 'date',
            'engagement_rate' => 'decimal:4',
            'selected_at' => 'datetime',
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
}
