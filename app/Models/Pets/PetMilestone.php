<?php

namespace App\Models\Pets;

use App\Models\Content\Post;
use App\Models\Identity\User;
use Database\Factories\Pets\PetMilestoneFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[UseFactory(PetMilestoneFactory::class)]
#[Fillable([
    'pet_id',
    'user_id',
    'post_id',
    'milestone_type',
    'title',
    'body',
    'body_html',
    'occurred_on',
    'share_as_post',
])]
class PetMilestone extends Model
{
    /** @use HasFactory<PetMilestoneFactory> */
    use HasFactory;

    use SoftDeletes;

    public const TYPE_LIFE_EVENT = 'life_event';

    public const TYPE_BIRTHDAY = 'birthday';

    public const TYPE_HEALTH = 'health';

    public const TYPE_TRAINING = 'training';

    public const TYPES = [
        self::TYPE_LIFE_EVENT,
        self::TYPE_BIRTHDAY,
        self::TYPE_HEALTH,
        self::TYPE_TRAINING,
    ];

    protected function casts(): array
    {
        return [
            'occurred_on' => 'date',
            'share_as_post' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Pet, $this>
     */
    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
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
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * @param  Builder<PetMilestone>  $query
     * @return Builder<PetMilestone>
     */
    public function scopeForPet(Builder $query, Pet $pet): Builder
    {
        return $query->where('pet_id', $pet->getKey());
    }

    /**
     * @param  Builder<PetMilestone>  $query
     * @return Builder<PetMilestone>
     */
    public function scopeChronological(Builder $query): Builder
    {
        return $query
            ->orderBy('occurred_on')
            ->orderBy('id');
    }
}
