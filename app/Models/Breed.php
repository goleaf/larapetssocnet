<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Breed extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'species_slug',
    ];

    public function species(): BelongsTo
    {
        return $this->belongsTo(Species::class, 'species_slug', 'slug');
    }

    public function pets(): HasMany
    {
        return $this->hasMany(Pet::class, 'breed', 'name');
    }
}
