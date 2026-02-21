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
        'user_id',
        'type',
        'title',
        'notes',
        'logged_at',
        'next_due_at',
        'metadata',
        'is_critical',
    ];

    protected function casts(): array
    {
        return [
            'logged_at' => 'datetime',
            'next_due_at' => 'datetime',
            'metadata' => 'array',
            'is_critical' => 'boolean',
        ];
    }

    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeRecent(Builder $query): Builder
    {
        return $query->latest('logged_at');
    }

    public function scopeCritical(Builder $query): Builder
    {
        return $query->where('is_critical', true);
    }

    public function scopeOfType(Builder $query, ?string $type): Builder
    {
        if (! $type) {
            return $query;
        }

        return $query->where('type', $type);
    }

    public function scopeDueSoon(Builder $query, int $days = 7): Builder
    {
        return $query
            ->whereNotNull('next_due_at')
            ->where('next_due_at', '<=', now()->addDays($days));
    }

    public function isDue(): bool
    {
        return $this->next_due_at !== null && $this->next_due_at->isPast();
    }
}
