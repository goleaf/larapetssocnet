<?php

namespace App\Models\Activities;

use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Models\Pets\Pet;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

#[Fillable([
    'contest_id',
    'user_id',
    'pet_id',
    'post_id',
    'caption',
    'votes_count',
    'is_winner',
])]
class ContestEntry extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;
    use SoftDeletes;

    public const MEDIA_COLLECTION_ENTRY_PHOTO = 'entry-photo';

    public const MEDIA_COLLECTION_ENTRY = 'entry-photo';

    public const MEDIA_CONVERSION_THUMB = 'thumb';

    public const MEDIA_CONVERSION_AVATAR = 'avatar';

    public const MEDIA_CONVERSION_CARD = 'card';

    public const MEDIA_CONVERSION_PREVIEW = 'preview';

    public const MEDIA_CONVERSION_LARGE = 'large';

    public const MEDIA_CONVERSION_COVER = 'cover';

    public const MEDIA_COLLECTION_ALLOWLIST_IMAGE = ['image/jpeg', 'image/png', 'image/webp'];

    protected function casts(): array
    {
        return [
            'votes_count' => 'integer',
            'is_winner' => 'boolean',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::MEDIA_COLLECTION_ENTRY_PHOTO)
            ->singleFile()
            ->acceptsMimeTypes(self::MEDIA_COLLECTION_ALLOWLIST_IMAGE);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion(self::MEDIA_CONVERSION_THUMB)
            ->fit(Fit::Crop, 150, 150)
            ->format('webp')
            ->quality(80)
            ->performOnCollections(self::MEDIA_COLLECTION_ENTRY_PHOTO)
            ->nonQueued();

        $this->addMediaConversion(self::MEDIA_CONVERSION_AVATAR)
            ->fit(Fit::Crop, 320, 320)
            ->format('webp')
            ->quality(82)
            ->performOnCollections(self::MEDIA_COLLECTION_ENTRY_PHOTO);

        $this->addMediaConversion(self::MEDIA_CONVERSION_CARD)
            ->width(600)
            ->format('webp')
            ->quality(84)
            ->performOnCollections(self::MEDIA_COLLECTION_ENTRY_PHOTO);

        $this->addMediaConversion(self::MEDIA_CONVERSION_PREVIEW)
            ->width(900)
            ->format('webp')
            ->quality(85)
            ->performOnCollections(self::MEDIA_COLLECTION_ENTRY_PHOTO);

        $this->addMediaConversion(self::MEDIA_CONVERSION_LARGE)
            ->width(1200)
            ->format('webp')
            ->quality(88)
            ->performOnCollections(self::MEDIA_COLLECTION_ENTRY_PHOTO);

        $this->addMediaConversion(self::MEDIA_CONVERSION_COVER)
            ->fit(Fit::Crop, 1200, 500)
            ->format('webp')
            ->quality(84)
            ->performOnCollections(self::MEDIA_COLLECTION_ENTRY_PHOTO);
    }

    public function contest(): BelongsTo
    {
        return $this->belongsTo(Contest::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(ContestVote::class, 'entry_id');
    }
}
