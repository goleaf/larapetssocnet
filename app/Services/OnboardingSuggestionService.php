<?php

namespace App\Services;

use App\Models\Identity\User;
use App\Models\Pets\Pet;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class OnboardingSuggestionService
{
    /**
     * @return Collection<int, User>
     */
    public function forUser(User $viewer, ?string $species = null, int $limit = 10): Collection
    {
        $limit = max(1, $limit);
        $normalizedSpecies = $this->normalizeSpecies($species);
        $suggestions = collect();

        if ($normalizedSpecies !== null) {
            $suggestions = $this->baseQuery($viewer)
                ->whereHas('pets', function (Builder $query) use ($normalizedSpecies): void {
                    $query
                        ->where('species', $normalizedSpecies)
                        ->where(function (Builder $visibilityQuery): void {
                            $visibilityQuery
                                ->where('visibility', 'public')
                                ->orWhere('is_public', true);
                        });
                })
                ->orderByDesc('followers_count')
                ->orderByDesc('post_reactions_received_count')
                ->orderByDesc('posts_count')
                ->limit($limit)
                ->get();
        }

        if ($suggestions->count() < $limit) {
            $fillers = $this->baseQuery($viewer)
                ->when($suggestions->isNotEmpty(), function (Builder $query) use ($suggestions): void {
                    $query->whereNotIn('users.id', $suggestions->pluck('id')->all());
                })
                ->orderByDesc('followers_count')
                ->orderByDesc('post_reactions_received_count')
                ->orderByDesc('post_comments_received_count')
                ->orderByDesc('posts_count')
                ->orderByDesc('pets_count')
                ->limit($limit - $suggestions->count())
                ->get();

            $suggestions = $suggestions->merge($fillers);
        }

        return $this->attachDescriptions($suggestions->take($limit)->values(), $normalizedSpecies);
    }

    /**
     * @return Builder<User>
     */
    private function baseQuery(User $viewer): Builder
    {
        $acceptedFollowing = $viewer->acceptedFollowing()->select('users.id');
        $pendingRequests = $viewer->sentPendingRequests()->select('users.id');

        return User::query()
            ->withPublicProfile()
            ->notBlockedFor($viewer)
            ->whereKeyNot($viewer->getKey())
            ->where('show_in_explore', true)
            ->whereNotIn('users.id', $acceptedFollowing)
            ->whereNotIn('users.id', $pendingRequests)
            ->with('media')
            ->select([
                'users.id',
                'users.name',
                'users.display_name',
                'users.username',
                'users.bio',
                'users.headline',
                'users.avatar_path',
                'users.profile_photo_path',
                'users.followers_count',
                'users.posts_count',
                'users.pets_count',
                'users.post_reactions_received_count',
                'users.post_comments_received_count',
                'users.is_private',
                'users.profile_visibility',
                'users.last_active_at',
            ]);
    }

    private function normalizeSpecies(?string $species): ?string
    {
        $normalized = Str::lower(trim((string) $species));

        return in_array($normalized, Pet::SPECIES, true) ? $normalized : null;
    }

    /**
     * @param  Collection<int, User>  $suggestions
     * @return Collection<int, User>
     */
    private function attachDescriptions(Collection $suggestions, ?string $species): Collection
    {
        foreach ($suggestions as $suggested) {
            $suggested->setAttribute('suggestion_description', $this->descriptionFor($suggested, $species));
        }

        return $suggestions;
    }

    private function descriptionFor(User $user, ?string $species): string
    {
        $description = trim((string) ($user->headline ?: $user->bio));

        if ($description === '' && $species !== null) {
            $description = 'Shares '.Str::plural($species).' moments and helpful community updates.';
        }

        if ($description === '') {
            $description = 'Shares pet stories and community moments on PetSocial.';
        }

        $description = (string) Str::of($description)->squish()->limit(120, '');

        if (! Str::endsWith($description, ['.', '!', '?'])) {
            $description .= '.';
        }

        return $description;
    }
}
