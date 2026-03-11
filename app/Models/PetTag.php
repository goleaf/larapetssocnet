<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PetTag extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'pet_id',
        'name',
        'slug',
    ];

    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }
}
