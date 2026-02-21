<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\EncodedImageInterface;

class MediaService
{
    public function handleImageUpload(UploadedFile $file, int $maxWidth = 1200): string
    {
        $manager = ImageManager::gd();
        $image = $manager->read($file->getPathname());

        if ($image->width() > $maxWidth) {
            $image->scale(width: $maxWidth);
        }

        /** @var EncodedImageInterface $encoded */
        $encoded = $image->toWebp(85);

        $path = sprintf(
            'uploads/%s/%s.webp',
            now()->format('Y/m/d'),
            bin2hex(random_bytes(12))
        );

        Storage::disk('public')->put($path, (string) $encoded);

        return $path;
    }
}
