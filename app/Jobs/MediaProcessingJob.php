<?php

namespace App\Jobs;

use App\Events\MediaUploaded;
use App\Models\Content\Post;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class MediaProcessingJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly string $temporaryPath,
        public readonly int $postId,
        public readonly string $mediaType,
        public readonly ?string $altText = null,
        public readonly int $order = 0,
    ) {
        $this->afterCommit();
    }

    public function handle(): void
    {
        $post = Post::query()->find($this->postId);

        if (! $post instanceof Post) {
            return;
        }

        $absolutePath = $this->absoluteTemporaryPath();

        if ($absolutePath === null) {
            return;
        }

        $mediaType = $this->mediaType === 'video' ? 'video' : 'image';
        $media = $post
            ->addMedia($absolutePath)
            ->withCustomProperties([
                'alt_text' => $this->altText,
                'processing_source' => 'post_creation_action',
            ])
            ->toMediaCollection($mediaType === 'video' ? 'videos' : 'photos', 'public');

        $post->postMedia()->create([
            'file_path' => $media->getPathRelativeToRoot(),
            'media_type' => $mediaType,
            'alt_text' => $this->altText,
            'processing_status' => 'processed',
            'order' => $this->order,
        ]);

        MediaUploaded::dispatch($media, $mediaType, (int) $post->user_id);
    }

    private function absoluteTemporaryPath(): ?string
    {
        if (File::exists($this->temporaryPath)) {
            return $this->temporaryPath;
        }

        $storagePath = Storage::path($this->temporaryPath);

        if (File::exists($storagePath)) {
            return $storagePath;
        }

        $livewirePath = Storage::disk(config('livewire.temporary_file_upload.disk') ?: config('filesystems.default'))
            ->path($this->temporaryPath);

        return File::exists($livewirePath) ? $livewirePath : null;
    }
}
