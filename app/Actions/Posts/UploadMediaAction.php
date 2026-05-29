<?php

declare(strict_types=1);

namespace App\Actions\Posts;

use App\Models\Content\Post;
use Illuminate\Http\UploadedFile;

class UploadMediaAction
{
    /**
     * @param  array<int, UploadedFile>  $mediaFiles
     */
    public function handle(Post $post, array $mediaFiles): void
    {
        collect($mediaFiles)
            ->values()
            ->each(function (UploadedFile $mediaFile, int $index) use ($post): void {
                $mediaType = str_starts_with((string) $mediaFile->getMimeType(), 'video/') ? 'video' : 'image';

                $storedMedia = $post
                    ->addMedia($mediaFile)
                    ->toMediaCollection($mediaType === 'video' ? 'videos' : 'photos', 'public');

                $post->postMedia()->create([
                    'file_path' => $storedMedia->getPathRelativeToRoot(),
                    'media_type' => $mediaType,
                    'processing_status' => 'processed',
                    'order' => $index,
                ]);
            });
    }
}
