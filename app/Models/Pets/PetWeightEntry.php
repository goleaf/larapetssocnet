<?php

namespace App\Models\Pets;

use App\Enums\Pets\PetWeightUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'pet_id',
    'entry_date',
    'weight_value',
    'weight_unit',
    'note',
])]
class PetWeightEntry extends Model
{
    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'weight_value' => 'decimal:2',
            'weight_unit' => PetWeightUnit::class,
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
