<?php

namespace App\Actions\Users;

use App\Models\Identity\User;
use App\Services\UsernameService;
use App\Support\Profiles\SocialLinkNormalizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mews\Purifier\Facades\Purifier;

class UpdateProfileAction
{
    public function __construct(private readonly UsernameService $usernames) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $user, array $data, bool $trackUsernameChange = false): User
    {
        $payload = $this->buildPayload($user, $data);

        if (array_key_exists('username', $data)) {
            $incomingUsername = (string) ($data['username'] ?? '');
            $currentUsername = (string) $user->getAttribute('username');

            if ($incomingUsername !== '' && $incomingUsername !== $currentUsername) {
                $this->usernames->change(
                    $user,
                    $incomingUsername,
                    $user,
                    'profile_update',
                    false
                );
            }
        }

        DB::transaction(function () use ($user, $payload): void {
            $user->fill($payload);

            if ($user->isDirty('email')) {
                $user->setAttribute('email_verified_at', null);
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
    private function buildPayload(User $user, array $data): array
    {
        $payload = [
            'name' => $data['name'] ?? $user->getAttribute('name'),
            'email' => isset($data['email']) ? Str::lower((string) $data['email']) : $user->getAttribute('email'),
        ];

        if (array_key_exists('bio', $data)) {
            $bioHtml = $this->sanitizeBioHtml($data['bio'] ?? null);
            $plainBio = $bioHtml ? trim(strip_tags($bioHtml)) : null;

            $payload['bio'] = $plainBio !== '' ? $plainBio : null;
            $payload['bio_html'] = $bioHtml;
        }

        if (array_key_exists('display_name', $data)) {
            $payload['display_name'] = ($data['display_name'] ?? null) ?: null;
        }

        if (array_key_exists('headline', $data)) {
            $payload['headline'] = ($data['headline'] ?? null) ?: null;
        }

        if (array_key_exists('pronouns', $data)) {
            $payload['pronouns'] = ($data['pronouns'] ?? null) ?: null;
        }

        if (array_key_exists('website', $data)) {
            $payload['website'] = $data['website'] ?? null;
        }

        if (array_key_exists('social_links', $data)) {
            $payload['social_links'] = SocialLinkNormalizer::forStorage($data['social_links'] ?? null);
        }

        if (array_key_exists('privacy_display_location', $data)) {
            $payload['privacy_display_location'] = (bool) $data['privacy_display_location'];
        }

        if (array_key_exists('privacy_display_birthdate', $data)) {
            $payload['privacy_display_birthdate'] = (bool) $data['privacy_display_birthdate'];
        }

        if (array_key_exists('location', $data) || array_key_exists('city', $data)) {
            $location = $data['location'] ?? $data['city'] ?? null;
            $payload['location'] = $location;
            $payload['city'] = $data['city'] ?? $location;

            if ($location === null) {
                $payload['location_lat'] = null;
                $payload['location_lng'] = null;
            }
        }

        if (array_key_exists('location_lat', $data)) {
            $payload['location_lat'] = $data['location_lat'] ?? null;
        }

        if (array_key_exists('location_lng', $data)) {
            $payload['location_lng'] = $data['location_lng'] ?? null;
        }

        if (array_key_exists('country_code', $data)) {
            $payload['country_code'] = $data['country_code'] ?? null;
        }

        if (array_key_exists('locale', $data)) {
            $payload['locale'] = ($data['locale'] ?? null) ?: null;
        }

        if (array_key_exists('timezone', $data)) {
            $payload['timezone'] = ($data['timezone'] ?? null) ?: null;
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
            $user->updateCover($data['cover'], $data['cover_photo_position'] ?? null);
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
                'cover_photo_position' => User::DEFAULT_COVER_PHOTO_POSITION,
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
