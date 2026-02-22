<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Badge extends Model
{
    use HasFactory;

    /** @var list<array<string, mixed>> */
    public const PREDEFINED = [
        ['slug' => 'first_post', 'name' => 'First Post', 'icon' => '📝', 'condition_type' => 'posts_count', 'condition_value' => 1],
        ['slug' => 'ten_posts', 'name' => 'Getting Started', 'icon' => '✍️', 'condition_type' => 'posts_count', 'condition_value' => 10],
        ['slug' => 'hundred_posts', 'name' => 'Prolific Poster', 'icon' => '🏆', 'condition_type' => 'posts_count', 'condition_value' => 100],
        ['slug' => 'first_follower', 'name' => 'Social Butterfly', 'icon' => '🦋', 'condition_type' => 'followers_count', 'condition_value' => 1],
        ['slug' => 'popular', 'name' => 'Popular', 'icon' => '⭐', 'condition_type' => 'followers_count', 'condition_value' => 100],
        ['slug' => 'pet_lover', 'name' => 'Pet Lover', 'icon' => '🐾', 'condition_type' => 'pets_count', 'condition_value' => 3],
        ['slug' => 'contest_winner', 'name' => 'Contest Winner', 'icon' => '🥇', 'condition_type' => 'manual', 'condition_value' => 0, 'type' => 'manual'],
        ['slug' => 'helpful_tip', 'name' => 'Helpful', 'icon' => '💡', 'condition_type' => 'manual', 'condition_value' => 0, 'type' => 'manual'],
        ['slug' => 'early_adopter', 'name' => 'Early Adopter', 'icon' => '🚀', 'condition_type' => 'manual', 'condition_value' => 0, 'type' => 'manual'],
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon',
        'color',
        'type',
        'condition_type',
        'condition_value',
    ];

    protected function casts(): array
    {
        return [
            'condition_value' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $badge): void {
            if (! $badge->slug && $badge->name) {
                $badge->slug = Str::slug((string) $badge->name);
            }
        });
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_badges')
            ->withPivot(['awarded_at', 'awarded_by', 'note']);
    }
}
