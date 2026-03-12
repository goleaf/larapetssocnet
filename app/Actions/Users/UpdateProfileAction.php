<?php

namespace App\Actions\Users;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mews\Purifier\Facades\Purifier;

class UpdateProfileAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $user, array $data, bool $trackUsernameChange = false): User
    {
        $payload = $this->buildPayload($user, $data, $trackUsernameChange);

        DB::transaction(function () use ($user, $payload): void {
            $user->fill($payload);

            if ($user->isDirty('email')) {
                $user->email_verified_at = null;
            }

            $user->save();
        });

        $this->syncMedia($user, $data);

        return $user->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function buildPayload(User $user, array $data, bool $trackUsernameChange): array
    {
        $currentUsername = (string) $user->username;
        $incomingUsername = (string) ($data['username'] ?? $currentUsername);

        $payload = [
            'name' => $data['name'] ?? $user->name,
            'username' => $incomingUsername,
            'email' => isset($data['email']) ? Str::lower((string) $data['email']) : $user->email,
        ];

        if ($trackUsernameChange && $incomingUsername !== '' && $incomingUsername !== $currentUsername) {
            $payload['username_changed_at'] = now();
        }

        if (array_key_exists('bio', $data)) {
            $bioHtml = $this->sanitizeBioHtml($data['bio'] ?? null);
            $plainBio = $bioHtml ? trim(strip_tags($bioHtml)) : null;

            $payload['bio'] = $plainBio !== '' ? $plainBio : null;
            $payload['bio_html'] = $bioHtml;
        }

        if (array_key_exists('website', $data)) {
            $payload['website'] = $data['website'] ?? null;
        }

        if (array_key_exists('location', $data) || array_key_exists('city', $data)) {
            $location = $data['location'] ?? $data['city'] ?? null;
            $payload['location'] = $location;
            $payload['city'] = $data['city'] ?? $location;
        }

        if (array_key_exists('country_code', $data)) {
            $payload['country_code'] = $data['country_code'] ?? null;
        }

        if (array_key_exists('birth_date', $data)) {
            $payload['birth_date'] = $data['birth_date'] ?? null;
        }

        if (array_key_exists('gender', $data)) {
            $payload['gender'] = $data['gender'] ?? null;
        }

        if (array_key_exists('is_private', $data)) {
            $payload['is_private'] = (bool) $data['is_private'];
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncMedia(User $user, array $data): void
    {
        if (($data['avatar'] ?? null) instanceof UploadedFile) {
            $user->updateAvatar($data['avatar']);
        }

        if (($data['cover'] ?? null) instanceof UploadedFile) {
            $user->updateCover($data['cover']);
        }

        if (array_key_exists('remove_avatar', $data) && (bool) $data['remove_avatar']) {
            $user->clearMediaCollection(User::MEDIA_COLLECTION_AVATAR);
            $user->forceFill([
                'avatar_path' => null,
                'profile_photo_path' => null,
            ])->saveQuietly();
        }

        if (array_key_exists('remove_cover', $data) && (bool) $data['remove_cover']) {
            $user->clearMediaCollection(User::MEDIA_COLLECTION_COVER);
            $user->forceFill([
                'cover_photo_path' => null,
            ])->saveQuietly();
        }
    }

    private function sanitizeBioHtml(?string $rawBio): ?string
    {
        $rawBio = trim((string) $rawBio);

        if ($rawBio === '') {
            return null;
        }

        $cleaned = trim((string) Purifier::clean($rawBio));

        return $cleaned !== '' ? $cleaned : null;
    }
}
