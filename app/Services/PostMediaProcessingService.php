<?php

namespace App\Services;

use App\Events\MediaUploaded;
use App\Models\Content\Post;
use App\Models\Content\PostMedia;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class PostMediaProcessingService
{
    private string $temporaryPath = '';

    private int $postId = 0;

    private string $mediaType = 'image';

    private ?string $altText = null;

    private int $order = 0;

    private ?int $postMediaId = null;

    public function process(
        string $temporaryPath,
        int $postId,
        string $mediaType,
        ?string $altText = null,
        int $order = 0,
        ?int $postMediaId = null,
    ): void {
        $this->temporaryPath = $temporaryPath;
        $this->postId = $postId;
        $this->mediaType = $mediaType;
        $this->altText = $altText;
        $this->order = $order;
        $this->postMediaId = $postMediaId;

        $post = Post::query()->find($this->postId);

        if (! $post instanceof Post) {
            return;
        }

        $postMedia = $this->postMediaRecord($post);
        $absolutePath = $this->absoluteTemporaryPath();

        if ($absolutePath === null) {
            $postMedia?->update(['processing_status' => 'failed']);

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

        $attributes = [
            'file_path' => $media->getPathRelativeToRoot(),
            'media_type' => $mediaType,
            'alt_text' => $this->altText,
            'processing_status' => 'ready',
            'order' => $this->order,
        ];

        if ($postMedia instanceof PostMedia) {
            $postMedia->update($attributes);
        } else {
            $post->postMedia()->create($attributes);
        }

        MediaUploaded::dispatch($media, $mediaType, (int) $post->user_id);
    }

    private function postMediaRecord(Post $post): ?PostMedia
    {
        if ($this->postMediaId === null) {
            return null;
        }

        return PostMedia::query()
            ->where('post_id', $post->getKey())
            ->whereKey($this->postMediaId)
            ->first();
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
