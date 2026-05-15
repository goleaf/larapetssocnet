<?php

namespace App\Models\Pets;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'slug',
    'species_slug',
])]
class Breed extends Model
{
    use HasFactory;

    public function species(): BelongsTo
    {
        return $this->belongsTo(Species::class, 'species_slug', 'slug');
    }

    public function pets(): HasMany
    {
        return $this->hasMany(Pet::class, 'breed', 'name');
    }
}
