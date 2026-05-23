@php
 $editableSocialLinks = \App\Support\Profiles\SocialLinkNormalizer::editable($user->social_links);
@endphp

<x-settings-layout>
 <div class="space-y-6" data-ui="settings-profile-page">
 <div class="space-y-2" data-ui="settings-page-header">
 <p class="chip min-h-8">Profile settings</p>
 <h2 class="shell-title text-2xl">Profile Information</h2>
 <p class="max-w-2xl text-sm leading-6 shell-text-muted">Update the identity, media, and public details people see across your posts and profile.</p>
 </div>

 <form action="{{ route('settings.profile.update') }}" method="POST" enctype="multipart/form-data" class="space-y-6" data-ui="settings-profile-form">
 @csrf
 @method('PUT')

 <div class="grid grid-cols-1 gap-y-6 sm:grid-cols-6 sm:gap-x-6">
 <div class="space-y-4 rounded-[var(--radius-card)] border border-whisker/40 bg-cream/30 p-4 sm:col-span-3" data-ui="profile-avatar-section">
 <x-ui.file-upload
 name="avatar"
 label="Avatar"
 accept="image/jpeg,image/png,image/webp"
 maxSize="3MB"
 preview
 help="JPG, PNG, or WEBP. Max 3MB. Square image recommended."
 />

 <div class="space-y-3">
 <p class="text-sm font-medium text-bark">Current avatar</p>
 <div class="flex items-center gap-4">
 <x-ui.avatar :src="$avatarUrl" :name="$user->name" size="xl"/>
 <span class="text-sm text-fur">Visible across your profile and posts.</span>
 </div>
 @if ($hasAvatar)
 <x-ui.checkbox name="remove_avatar" label="Remove current avatar"/>
 @endif
 </div>
 </div>

 <div class="space-y-4 rounded-[var(--radius-card)] border border-whisker/40 bg-cream/30 p-4 sm:col-span-3" data-ui="profile-cover-section">
 <x-ui.file-upload
 name="cover"
 label="Cover Photo"
 accept="image/jpeg,image/png,image/webp,image/gif"
 maxSize="5MB"
 preview
 help="JPG, PNG, WEBP, or GIF. Minimum 1200×400, recommended 1600×480."
 />

 @if ($hasCover)
 <div class="space-y-3">
 <p class="text-sm font-medium text-bark">Current cover</p>
 <img src="{{ $coverUrl }}" alt="{{ $user->name }} cover" class="h-36 w-full rounded-xl object-cover">
 <x-ui.checkbox name="remove_cover" label="Remove current cover photo"/>
 </div>
 @endif
 </div>

 <div class="sm:col-span-3">
 <x-ui.input id="name" name="name" label="Name" :value="old('name', $user->name)" required autofocus autocomplete="name"/>
 </div>

 <div class="sm:col-span-3">
 <x-ui.input id="display_name" name="display_name" type="text" label="Display name" :value="old('display_name', $user->display_name)" autocomplete="nickname"
 hint="Shown publicly instead of your account name when set."/>
 </div>

 <div class="sm:col-span-3"
 x-data="{ currentUsername:'{{ $user->username }}', newUsername:'{{ old('username', $user->username) }}'}">
 <x-ui.input id="username" name="username" label="Username"
 x-model="newUsername" required autocomplete="username"/>

 @if (! $user->canChangeUsername())
 <p class="mt-2 text-xs text-amber-700">
 You can change your username again in {{ $user->daysUntilUsernameChange() }} day(s).
 </p>
 @endif

 <div x-show="currentUsername !== newUsername && newUsername !==''" style="display: none;">
 <x-ui.alert type="warning" title="Username change warning">
 <p>
 Changing your username will change your profile URL
 (<code>{{ url('/@') }}<span x-text="newUsername"></span></code>). Old links will redirect,
 and your previous username will be reserved.
 </p>
 <div class="mt-3">
 <x-ui.input id="username_confirm" name="username_confirm" label="Type your current username to confirm"/>
 </div>
 </x-ui.alert>
 </div>
 </div>

 <div class="sm:col-span-6">
 <x-ui.input id="email" name="email" type="email" label="Email Address" :value="old('email', $user->email)" required autocomplete="email"/>
 </div>

 <div class="sm:col-span-6">
 <x-ui.textarea id="bio" name="bio" rows="4" label="Bio" :value="old('bio', $user->bio)"
 hint="Brief description for your profile. URLs are hyperlinked."/>
 </div>

 <div class="sm:col-span-6">
 <x-ui.input id="headline" name="headline" type="text" label="Headline" :value="old('headline', $user->headline)"
 hint="Short status or tagline shown near your name."/>
 </div>

 <div class="sm:col-span-3">
 <x-ui.input id="pronouns" name="pronouns" type="text" label="Pronouns" :value="old('pronouns', $user->pronouns)" placeholder="she/her, he/him, they/them"/>
 </div>

 <div class="sm:col-span-3">
 <x-ui.input id="location" name="location" type="text" label="Location"
 :value="old('location', $user->location)"/>
 </div>

 <div class="sm:col-span-3">
 <x-ui.input id="website" name="website" type="url" label="Website"
 :value="old('website', $user->website)"/>
 </div>

 <div class="rounded-[var(--radius-card)] border border-whisker/40 bg-cream/30 p-4 sm:col-span-6" data-ui="profile-social-section">
 <p class="text-sm font-medium text-bark">Social links</p>
 <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
 <div>
 <x-ui.input id="social_links_x" name="social_links[x]" type="text" label="Twitter/X username"
 :value="old('social_links.x', $editableSocialLinks['x'] ?? null)" placeholder="@username" prefix="X"/>
 </div>
 <div>
 <x-ui.input id="social_links_instagram" name="social_links[instagram]" type="text" label="Instagram username"
 :value="old('social_links.instagram', $editableSocialLinks['instagram'] ?? null)" placeholder="@username" prefix="IG"/>
 </div>
 <div>
 <x-ui.input id="social_links_facebook" name="social_links[facebook]" type="url" label="Facebook profile URL"
 :value="old('social_links.facebook', $editableSocialLinks['facebook'] ?? null)" placeholder="https://facebook.com/username" prefix="f"/>
 </div>
 <div>
 <x-ui.input id="social_links_youtube" name="social_links[youtube]" type="url" label="YouTube channel URL"
 :value="old('social_links.youtube', $editableSocialLinks['youtube'] ?? null)" placeholder="https://youtube.com/@username" prefix="YT"/>
 </div>
 </div>
 </div>

 <div class="sm:col-span-3">
 <x-ui.select
 id="gender"
 name="gender"
 label="Gender"
 :options="[
 '' => 'Select...',
 'male' => 'Male',
 'female' => 'Female',
 'other' => 'Other',
 'prefer_not_to_say' => 'Prefer not to say',
 ]"
 :selected="old('gender', $user->gender)"
 />
 </div>

 <div class="sm:col-span-3">
 <x-ui.input id="birth_date" name="birth_date" type="date" label="Birth Date"
 :value="old('birth_date', $user->birth_date ? $user->birth_date->format('Y-m-d') : '')"/>
 </div>

 <div class="sm:col-span-3">
 <x-ui.input id="locale" name="locale" type="text" label="Language" :value="old('locale', $user->locale)" placeholder="en, en_US"/>
 </div>

 <div class="sm:col-span-3">
 <x-ui.input id="timezone" name="timezone" type="text" label="Timezone" :value="old('timezone', $user->timezone)" placeholder="Europe/Vilnius"/>
 </div>

 </div>

 <div class="flex justify-end border-t border-whisker/30 pt-5">
 <x-ui.button type="submit" variant="primary" class="min-h-11 sm:min-w-36">Save Profile</x-ui.button>
 </div>
 </form>

 @php
 $selectedPortfolioPostIds = collect(old('portfolio_posts', $portfolioPostIds ?? []))
 ->filter(fn ($postId): bool => is_numeric($postId))
 ->map(fn ($postId): int => (int) $postId)
 ->values()
 ->all();
 $selectedPortfolioPositions = collect($portfolioPositions ?? [])
 ->mapWithKeys(fn ($position, $postId): array => [(int) $postId => (int) $position])
 ->all();
 @endphp

 <section class="space-y-5 border-t border-whisker/30 pt-6" data-ui="settings-profile-portfolio">
 <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-start">
 <div class="space-y-2">
 <p class="chip min-h-8">Portfolio mode</p>
 <h3 class="shell-title text-xl">Curate your public portfolio</h3>
 <p class="max-w-2xl text-sm leading-6 text-fur">
 Choose up to 12 published public posts for a magazine-style page at your shareable portfolio link.
 </p>
 </div>
 <x-ui.button :href="$portfolioUrl" target="_blank" rel="noopener noreferrer" variant="default" class="min-h-11">
 View Portfolio
 </x-ui.button>
 </div>

 @error('portfolio_posts')
 <x-ui.alert type="error" title="Portfolio could not be saved">
 {{ $message }}
 </x-ui.alert>
 @enderror

 <form action="{{ route('settings.profile.portfolio.update') }}" method="POST" class="space-y-4" data-ui="settings-profile-portfolio-form">
 @csrf
 @method('PUT')

 @if (($portfolioPosts ?? collect())->isEmpty())
 <div class="rounded-[var(--radius-card)] border border-dashed border-whisker/60 bg-cream/30 px-5 py-6 text-sm leading-6 text-fur" data-ui="settings-profile-portfolio-empty">
 Portfolio posts must be published, public, and visible to guests. Publish a public post to start building your showcase.
 </div>
 @else
 <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
 @foreach ($portfolioPosts as $post)
 @php
 $postId = (int) $post->getKey();
 $isSelected = in_array($postId, $selectedPortfolioPostIds, true);
 $mediaItem = $post->mediaItemsForDisplay()->first();
 $mediaUrl = $mediaItem ? \App\Models\Content\Post::mediaItemUrl($mediaItem) : '';
 $position = old('portfolio_positions.'.$postId, $selectedPortfolioPositions[$postId] ?? min($loop->iteration, 12));
 @endphp
 <article class="rounded-[var(--radius-card)] border border-whisker/40 bg-warm-white p-3" data-ui="settings-profile-portfolio-option">
 <div class="grid grid-cols-[5rem_minmax(0,1fr)] gap-3">
 <div class="aspect-square overflow-hidden rounded-[var(--radius-soft)] bg-cream">
 @if ($mediaUrl !== '')
 <img src="{{ $mediaUrl }}" alt="{{ $user->name }} portfolio post media" class="h-full w-full object-cover" loading="lazy">
 @else
 <div class="flex h-full w-full items-center justify-center {{ $user->profile_default_gradient }}">
 <span class="px-2 text-center text-xs font-bold uppercase text-fur">Text</span>
 </div>
 @endif
 </div>

 <div class="min-w-0">
 <x-ui.checkbox
 name="portfolio_posts[]"
 id="portfolio_post_{{ $postId }}"
 :value="$postId"
 :checked="$isSelected"
 :label="\Illuminate\Support\Str::limit(strip_tags((string) ($post->body_html ?: $post->body)), 64)"
 />
 <div class="mt-3 grid grid-cols-[5.5rem_minmax(0,1fr)] items-center gap-2">
 <label for="portfolio_position_{{ $postId }}" class="text-xs font-semibold text-fur">Position</label>
 <input
 id="portfolio_position_{{ $postId }}"
 name="portfolio_positions[{{ $postId }}]"
 type="number"
 min="1"
 max="12"
 value="{{ $position }}"
 class="form-input h-9 w-full text-sm"
 aria-label="Portfolio position for post {{ $loop->iteration }}">
 </div>
 <p class="mt-2 text-xs text-fur">{{ number_format((int) $post->reactions_count) }} reactions · {{ number_format((int) $post->comments_count) }} comments</p>
 </div>
 </div>
 </article>
 @endforeach
 </div>

 <div class="flex flex-col gap-3 border-t border-whisker/30 pt-4 sm:flex-row sm:items-center sm:justify-between">
 <p class="text-sm text-fur">The first slot becomes the large feature; slots 2-4 are secondary features; the rest render as accent tiles.</p>
 <x-ui.button type="submit" variant="primary" class="min-h-11 sm:min-w-40">Save Portfolio</x-ui.button>
 </div>
 @endif
 </form>
 </section>
 </div>
</x-settings-layout>
