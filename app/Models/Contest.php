<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Contest extends Model
{
    use HasFactory;
    use SoftDeletes;

    /** @var list<string> */
    public const STATUSES = ['draft', 'active', 'voting', 'ended', 'cancelled'];

    /** @var array<string, list<string>> */
    public const TRANSITIONS = [
        'draft' => ['active'],
        'active' => ['voting', 'cancelled'],
        'voting' => ['ended', 'cancelled'],
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organizer_user_id',
        'title',
        'slug',
        'description',
        'prize',
        'species',
        'starts_at',
        'ends_at',
        'max_entries',
        'entries_count',
        'winner_entry_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'max_entries' => 'integer',
            'entries_count' => 'integer',
            'winner_entry_id' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $contest): void {
            if (! $contest->slug && $contest->title) {
                $contest->slug = static::generateUniqueSlug((string) $contest->title);
            }
        });
    }

    public static function generateUniqueSlug(string $seed): string
    {
        $base = Str::slug($seed) ?: 'contest';
        $slug = $base;
        $suffix = 1;

        while (static::query()->where('slug', $slug)->exists()) {
            $suffix++;
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_user_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(ContestEntry::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(ContestVote::class);
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(ContestEntry::class, 'winner_entry_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeVoting(Builder $query): Builder
    {
        return $query->where('status', 'voting');
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->whereIn('status', ['active', 'voting', 'ended']);
    }

    public function hasEntered(User $user): bool
    {
        return $this->entries()->where('user_id', $user->id)->exists();
    }

    public function hasVoted(User $user): bool
    {
        return $this->votes()->where('user_id', $user->id)->exists();
    }

    public function isActive(): bool
    {
        return $this->status === 'active'
            && now()->between($this->starts_at, $this->ends_at);
    }
}
