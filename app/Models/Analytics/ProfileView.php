<?php

namespace App\Models\Analytics;

use App\Models\Identity\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'profile_user_id',
    'viewer_user_id',
    'viewed_on',
    'views_count',
])]
class ProfileView extends Model
{
    use HasFactory;

    public const RECENT_UNIQUE_VIEWER_DAYS = 30;

    protected function casts(): array
    {
        return [
            'viewed_on' => 'date',
            'views_count' => 'integer',
        ];
    }

    /**
     * @return Attribute<string, mixed>
     */
    protected function viewedOn(): Attribute
    {
        return Attribute::make(
            set: fn (mixed $value): string => CarbonImmutable::parse($value)->toDateString(),
        );
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function profileUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'profile_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function viewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'viewer_user_id');
    }

    /**
     * @param  Builder<ProfileView>  $query
     * @return Builder<ProfileView>
     */
    public function scopeForProfile(Builder $query, User|int $profileUser): Builder
    {
        $profileUserId = $profileUser instanceof User
            ? (int) $profileUser->getKey()
            : $profileUser;

        return $query->where('profile_user_id', $profileUserId);
    }

    public static function uniqueViewerCountForProfile(User|int $profileUser, string $startDate, string $endDate): int
    {
        $profileUserId = $profileUser instanceof User
            ? (int) $profileUser->getKey()
            : $profileUser;
        $inclusiveStartDate = CarbonImmutable::parse($startDate)->toDateString();
        $exclusiveEndDate = CarbonImmutable::parse($endDate)->addDay()->toDateString();

        return (int) self::query()
            ->forProfile($profileUserId)
            ->where('viewer_user_id', '!=', $profileUserId)
            ->where('viewed_on', '>=', $inclusiveStartDate)
            ->where('viewed_on', '<', $exclusiveEndDate)
            ->distinct('viewer_user_id')
            ->count('viewer_user_id');
    }
}
