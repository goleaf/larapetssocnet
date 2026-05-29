<?php

namespace App\Models\Pets;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'pet_id',
    'reminder_type',
    'custom_text',
    'frequency_days',
    'last_sent_on',
    'next_due_on',
])]
class PetHealthReminder extends Model
{
    public const TYPE_VACCINATION = 'vaccination';

    public const TYPE_FLEA_TREATMENT = 'flea_treatment';

    public const TYPE_WORMING = 'worming';

    public const TYPE_DENTAL_CHECK = 'dental_check';

    public const TYPE_VET_CHECKUP = 'vet_checkup';

    public const TYPE_GROOMING = 'grooming';

    public const TYPE_CUSTOM = 'custom';

    protected function casts(): array
    {
        return [
            'frequency_days' => 'integer',
            'last_sent_on' => 'date',
            'next_due_on' => 'date',
        ];
    }

    /**
     * @return list<string>
     */
    public static function types(): array
    {
        return [
            self::TYPE_VACCINATION,
            self::TYPE_FLEA_TREATMENT,
            self::TYPE_WORMING,
            self::TYPE_DENTAL_CHECK,
            self::TYPE_VET_CHECKUP,
            self::TYPE_GROOMING,
            self::TYPE_CUSTOM,
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
