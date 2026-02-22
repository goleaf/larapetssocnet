<?php

namespace App\Services;

use App\Models\Pet;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PetService
{
    public function __construct(
        private ContentService $content,
        private MediaService $media,
    ) {}

    public function create(User $owner, array $data, ?UploadedFile $avatar = null): Pet
    {
        return DB::transaction(function () use ($owner, $data, $avatar): Pet {
            $bio = $data['bio'] ?? null;
            $bioHtml = $bio ? $this->content->process($bio) : null;
            $slug = $this->generateSlug($data['name'], $owner->username ?? (string) $owner->id);

            $pet = Pet::create([
                'user_id' => $owner->id,
                'name' => $data['name'],
                'slug' => $slug,
                'species' => $data['species'] ?? 'other',
                'breed' => $data['breed'] ?? null,
                'gender' => $data['gender'] ?? 'unknown',
                'size' => $data['size'] ?? null,
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'age_text' => $data['age_text'] ?? null,
                'bio' => $bio,
                'bio_html' => $bioHtml,
                'is_deceased' => $data['is_deceased'] ?? false,
                'personality_tags' => $data['personality_tags'] ?? [],
            ]);

            if ($avatar) {
                $pet->addMedia($avatar)->toMediaCollection('avatar');
            }

            $owner->increment('pets_count');

            return $pet;
        });
    }

    public function update(Pet $pet, array $data, ?UploadedFile $avatar = null): Pet
    {
        return DB::transaction(function () use ($pet, $data, $avatar): Pet {
            $bio = $data['bio'] ?? $pet->bio;
            $bioHtml = $bio ? $this->content->process($bio) : null;

            $pet->update(array_filter([
                'name' => $data['name'] ?? $pet->name,
                'species' => $data['species'] ?? $pet->species,
                'breed' => array_key_exists('breed', $data) ? $data['breed'] : $pet->breed,
                'gender' => $data['gender'] ?? $pet->gender,
                'size' => array_key_exists('size', $data) ? $data['size'] : $pet->size,
                'date_of_birth' => array_key_exists('date_of_birth', $data) ? $data['date_of_birth'] : $pet->date_of_birth,
                'age_text' => array_key_exists('age_text', $data) ? $data['age_text'] : $pet->age_text,
                'bio' => $bio,
                'bio_html' => $bioHtml,
                'is_deceased' => $data['is_deceased'] ?? $pet->is_deceased,
                'personality_tags' => $data['personality_tags'] ?? $pet->personality_tags,
            ], fn ($v) => $v !== null));

            if ($avatar) {
                $pet->clearMediaCollection('avatar');
                $pet->addMedia($avatar)->toMediaCollection('avatar');
            }

            return $pet->fresh();
        });
    }

    public function delete(Pet $pet): void
    {
        DB::transaction(function () use ($pet): void {
            $pet->owner->decrement('pets_count');
            $pet->delete();
        });
    }

    public function addGalleryPhoto(Pet $pet, UploadedFile $file): void
    {
        $count = $pet->getMedia('gallery')->count();

        if ($count >= 30) {
            throw new \RuntimeException('Gallery is full (max 30 photos).');
        }

        $pet->addMedia($file)->toMediaCollection('gallery');
    }

    public function removeGalleryPhoto(Pet $pet, int $mediaId): void
    {
        $media = $pet->getMedia('gallery')->firstWhere('id', $mediaId);

        if (! $media) {
            throw new \RuntimeException("Media #{$mediaId} not found in gallery.");
        }

        $media->delete();
    }

    private function generateSlug(string $name, string $username): string
    {
        $base = Str::slug($name.'-'.$username);
        $slug = $base;
        $i = 1;

        while (Pet::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
