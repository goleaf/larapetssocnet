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
 help="JPG, PNG, WEBP, or GIF. Recommended 1600×480."
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
 <x-ui.input id="social_links_x" name="social_links[x]" type="url" label="X / Twitter"
 :value="old('social_links.x', $user->social_links['x'] ?? null)" placeholder="https://x.com/username"/>
 </div>
 <div>
 <x-ui.input id="social_links_instagram" name="social_links[instagram]" type="url" label="Instagram"
 :value="old('social_links.instagram', $user->social_links['instagram'] ?? null)" placeholder="https://instagram.com/username"/>
 </div>
 <div>
 <x-ui.input id="social_links_tiktok" name="social_links[tiktok]" type="url" label="TikTok"
 :value="old('social_links.tiktok', $user->social_links['tiktok'] ?? null)" placeholder="https://tiktok.com/@username"/>
 </div>
 <div>
 <x-ui.input id="social_links_youtube" name="social_links[youtube]" type="url" label="YouTube"
 :value="old('social_links.youtube', $user->social_links['youtube'] ?? null)" placeholder="https://youtube.com/@username"/>
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
 </div>
</x-settings-layout>
