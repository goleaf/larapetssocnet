<?php

namespace App\Models\Pets;

use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

#[Fillable([
    'user_id',
    'title',
    'description',
    'cover_media_id',
])]
class PhotoGallery extends Model
{
    use HasFactory;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function media(): BelongsToMany
    {
        return $this->belongsToMany(Media::class, 'photo_gallery_media', 'gallery_id', 'media_id')
            ->withTimestamps()
            ->orderBy('photo_gallery_media.order');
    }

    public function coverMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'cover_media_id');
    }

    public function coverUrl(): string
    {
        $media = $this->coverMedia ?: $this->media->first();

        return $media ? $media->getUrl() : '';
    }
}
