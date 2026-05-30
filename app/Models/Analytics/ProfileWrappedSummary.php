<?php

namespace App\Models\Analytics;

use App\Models\Content\Post;
use App\Models\Content\Reaction;
use App\Models\Identity\User;
use Carbon\CarbonImmutable;
use Database\Factories\Analytics\ProfileWrappedSummaryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[UseFactory(ProfileWrappedSummaryFactory::class)]
#[Fillable([
    'user_id',
    'year',
    'total_posts_published',
    'total_reactions_received',
    'top_reaction_type',
    'top_reaction_count',
    'most_active_month',
    'most_active_month_posts',
    'new_followers_count',
    'pets_added_count',
    'most_engaged_post_id',
    'most_engaged_post_score',
    'share_image_path',
    'generated_at',
    'share_image_generated_at',
])]
class ProfileWrappedSummary extends Model
{
    /** @use HasFactory<ProfileWrappedSummaryFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'total_posts_published' => 'integer',
            'total_reactions_received' => 'integer',
            'top_reaction_count' => 'integer',
            'most_active_month' => 'integer',
            'most_active_month_posts' => 'integer',
            'new_followers_count' => 'integer',
            'pets_added_count' => 'integer',
            'most_engaged_post_score' => 'integer',
            'generated_at' => 'datetime',
            'share_image_generated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Post, $this>
     */
    public function mostEngagedPost(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'most_engaged_post_id');
    }

    /**
     * @param  Builder<ProfileWrappedSummary>  $query
     * @return Builder<ProfileWrappedSummary>
     */
    public function scopeForUser(Builder $query, User|int $user): Builder
    {
        $userId = $user instanceof User ? (int) $user->getKey() : $user;

        return $query->where('user_id', $userId);
    }

    /**
     * @param  Builder<ProfileWrappedSummary>  $query
     * @return Builder<ProfileWrappedSummary>
     */
    public function scopeForYear(Builder $query, int $year): Builder
    {
        return $query->where('year', $year);
    }

    public function generatedShareImageUrl(): ?string
    {
        return filled($this->share_image_path)
            ? Storage::disk('public')->url((string) $this->share_image_path)
            : null;
    }

    public function formattedMostActiveMonthLabel(): string
    {
        if (! $this->most_active_month) {
            return 'No active month';
        }

        return CarbonImmutable::create((int) $this->year, (int) $this->most_active_month, 1)->format('F');
    }

    public function formattedTopReactionLabel(): string
    {
        if (! $this->top_reaction_type) {
            return 'No reactions yet';
        }

        $type = Reaction::normalizeType((string) $this->top_reaction_type);

        return Reaction::labelMap()[$type] ?? Str::headline($type);
    }

    /**
     * @return Attribute<string, never>
     */
    protected function mostActiveMonthLabel(): Attribute
    {
        return Attribute::get(fn (): string => $this->formattedMostActiveMonthLabel());
    }

    /**
     * @return Attribute<string, never>
     */
    protected function topReactionLabel(): Attribute
    {
        return Attribute::get(fn (): string => $this->formattedTopReactionLabel());
    }
}
