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

    public const TYPE_WEIGHT = 'weight';

    public const TYPE_MEDICATION = 'medication';

    public const TYPE_VACCINATION = 'vaccination';

    public const TYPE_VACCINE_LEGACY = 'vaccine';

    public const TYPE_VET_VISIT = 'vet_visit';

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
        'next_due_at',
    ];

    protected function casts(): array
    {
        return [
            'logged_at' => 'datetime',
            'next_due_at' => 'datetime',
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

    public function getTypeLabelAttribute(): string
    {
        $type = $this->log_type === self::TYPE_VACCINE_LEGACY
            ? self::TYPE_VACCINATION
            : $this->log_type;

        return match ($type) {
            self::TYPE_WEIGHT => 'Weight',
            self::TYPE_MEDICATION => 'Medication',
            self::TYPE_VACCINATION => 'Vaccination',
            self::TYPE_VET_VISIT => 'Vet Visit',
            default => ucfirst(str_replace('_', ' ', (string) $type)),
        };
    }
}
