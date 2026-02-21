<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PetHealthLog extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'pet_id',
        'logged_by_user_id',
        'log_type',
        'title',
        'notes',
        'weight_kg',
        'temperature_c',
        'logged_at',
    ];

    protected function casts(): array
    {
        return [
            'logged_at' => 'datetime',
            'weight_kg' => 'decimal:2',
            'temperature_c' => 'decimal:1',
        ];
    }

    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'logged_by_user_id');
    }

    public function scopeRecent(Builder $query): Builder
    {
        return $query->latest('logged_at');
    }

    public function scopeOfType(Builder $query, ?string $type): Builder
    {
        if (! $type) {
            return $query;
        }

        return $query->where('log_type', $type);
    }
}
