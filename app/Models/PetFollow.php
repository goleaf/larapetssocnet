<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class PetFollow extends Pivot
{
    protected $table = 'pet_followers';

    public $incrementing = false;

    public $timestamps = true;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'pet_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }
}
