<?php

namespace App\Models\Pets;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'source_pet_id',
    'target_pet_id',
    'relationship_type',
    'note',
])]
class PetRelationship extends Model
{
    public const TYPE_PARENT = 'parent';

    public const TYPE_OFFSPRING = 'offspring';

    public const TYPE_SIBLING = 'sibling';

    public const TYPE_MATE = 'mate';

    /**
     * @return array<string, string>
     */
    public static function inverseTypes(): array
    {
        return [
            self::TYPE_PARENT => self::TYPE_OFFSPRING,
            self::TYPE_OFFSPRING => self::TYPE_PARENT,
            self::TYPE_SIBLING => self::TYPE_SIBLING,
            self::TYPE_MATE => self::TYPE_MATE,
        ];
    }

    /**
     * @return BelongsTo<Pet, $this>
     */
    public function sourcePet(): BelongsTo
    {
        return $this->belongsTo(Pet::class, 'source_pet_id');
    }

    /**
     * @return BelongsTo<Pet, $this>
     */
    public function targetPet(): BelongsTo
    {
        return $this->belongsTo(Pet::class, 'target_pet_id');
    }
}
