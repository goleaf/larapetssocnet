<?php

namespace App\Models\Pets;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'pet_id',
    'name',
    'slug',
])]
class PetTag extends Model
{
    use HasFactory;

    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }
}
