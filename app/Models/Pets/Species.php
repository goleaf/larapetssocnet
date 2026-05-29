<?php

namespace App\Models\Pets;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'slug',
    'icon_identifier',
    'color_identifier',
    'gradient_from',
    'gradient_to',
    'display_order',
    'life_stage_config',
])]
class Species extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
            'life_stage_config' => 'array',
        ];
    }

    public function pets(): HasMany
    {
        return $this->hasMany(Pet::class, 'species_id');
    }

    public function breeds(): HasMany
    {
        return $this->hasMany(Breed::class);
    }
}
