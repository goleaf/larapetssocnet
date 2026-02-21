<?php

namespace App\Services;

use App\Contracts\MediaService;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class SpatieMediaService implements MediaService
{
    public function storeUserImage(User $user, UploadedFile $file, string $collection): void
    {
        $processedPath = $this->processImage($file, $collection);

        $extension = $processedPath === null
            ? ($file->guessExtension() ?: 'jpg')
            : 'jpg';

        $fileName = $collection.'-'.Str::uuid().'.'.$extension;

        $user->addMedia($processedPath ?? $file->getRealPath())
            ->usingName($collection)
            ->usingFileName($fileName)
            ->toMediaCollection($collection);

        if ($processedPath !== null && is_file($processedPath)) {
            @unlink($processedPath);
        }
    }

    protected function processImage(UploadedFile $file, string $collection): ?string
    {
        try {
            $manager = new ImageManager(new Driver);
            $image = $manager->read($file->getRealPath());

            if ($collection === 'avatar') {
                $image = $image->cover(512, 512);
            }

            if ($collection === 'cover') {
                $image = $image->scaleDown(1600, 900);
            }

            $directory = storage_path('app/tmp/profile-media');
            if (! is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            $path = $directory.'/'.Str::uuid().'.jpg';
            $image->toJpeg(85)->save($path);

            return $path;
        } catch (\Throwable) {
            return null;
        }
    }
}
