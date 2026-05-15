<?php

namespace App\Models\Pets;

use App\Models\Identity\User;
use Database\Factories\PetHealthLogFactory;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Throwable;

#[UseFactory(PetHealthLogFactory::class)]
#[Fillable([
    'pet_id',
    'logged_by_user_id',
    'log_type',
    'title',
    'notes',
    'weight_kg',
    'temperature_c',
    'logged_at',
    'next_due_at',
])]
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
     * @var array<string, bool>
     */
    protected static array $columnCache = [];

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

    public function scopeForPet(Builder $query, Pet $pet): Builder
    {
        return $query->where('pet_id', $pet->getKey());
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query
            ->whereNotNull('next_due_at')
            ->where('next_due_at', '>=', now()->startOfDay())
            ->orderBy('next_due_at');
    }

    public function scopeWeightTrendSeries(Builder $query): Builder
    {
        return $query
            ->where('log_type', self::TYPE_WEIGHT)
            ->whereNotNull('weight_kg')
            ->orderBy('logged_at');
    }

    public static function paginateForPet(Pet $pet, int $perPage = 15): LengthAwarePaginator
    {
        return self::query()
            ->forPet($pet)
            ->latest('logged_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @return Collection<int, self>
     */
    public static function upcomingForPet(Pet $pet, int $limit = 10): Collection
    {
        return self::query()
            ->forPet($pet)
            ->upcoming()
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, self>
     */
    public static function weightTrendForPet(Pet $pet, int $limit = 30): Collection
    {
        $query = self::query()
            ->forPet($pet)
            ->weightTrendSeries()
            ->limit($limit);

        if (self::hasColumn('pet_health_logs', 'title')) {
            $query->select(['logged_at', 'weight_kg', 'title']);
        } else {
            $query->select(['logged_at', 'weight_kg']);
        }

        return $query->get();
    }

    public static function findForPet(Pet $pet, string|int $healthLogId): ?self
    {
        return self::query()
            ->forPet($pet)
            ->whereKey($healthLogId)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function createForPet(Pet $pet, ?Authenticatable $user, array $payload): self
    {
        return self::query()->create([
            ...$payload,
            'pet_id' => $pet->getKey(),
            'logged_by_user_id' => $user?->getAuthIdentifier(),
        ]);
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

    protected static function hasColumn(string $table, string $column): bool
    {
        $cacheKey = "{$table}.{$column}";

        if (! array_key_exists($cacheKey, static::$columnCache)) {
            try {
                static::$columnCache[$cacheKey] = Schema::hasColumn($table, $column);
            } catch (Throwable) {
                static::$columnCache[$cacheKey] = false;
            }
        }

        return static::$columnCache[$cacheKey];
    }
}
