<x-settings-layout>
 <div class="space-y-6">
 <div>
 <h3 class="text-lg font-medium leading-6 text-gray-900">Privacy & Visibility</h3>
 <p class="mt-1 text-sm text-gray-500">Manage who can see your content and who can interact with you.</p>
 </div>

 <form action="{{ route('settings.privacy.update') }}" method="POST" class="space-y-8">
 @csrf
 @method('PUT')

 <div class="space-y-6">
 <!-- Profile Visibility -->
 <div>
 <h4 class="text-sm font-medium text-gray-900">Profile Visibility</h4>
 <p class="text-sm text-gray-500 mb-4">Control who can view your posts and personal details.</p>

 <div class="space-y-4">
 <div class="flex items-center">
 <input id="pv_public" name="profile_visibility" type="radio" value="public"
 class="h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-500"
 @checked(old('profile_visibility', $user->profile_visibility) ==='public')>
 <label for="pv_public" class="ml-3 block text-sm font-medium leading-6 text-gray-900">
 Public <span class="font-normal text-gray-500">(Anyone can see your profile and
 posts)</span>
 </label>
 </div>
 <div class="flex items-center">
 <input id="pv_followers" name="profile_visibility" type="radio" value="followers_only"
 class="h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-500"
 @checked(old('profile_visibility', $user->profile_visibility) ==='followers_only')>
 <label for="pv_followers" class="ml-3 block text-sm font-medium leading-6 text-gray-900">
 Followers Only <span class="font-normal text-gray-500">(Only approved followers can see
 your profile and posts)</span>
 </label>
 </div>
 </div>
 <x-input-error class="mt-2" :messages="$errors->get(' profile_visibility')"/>
 </div>

 <hr class="border-gray-200">

 <!-- Messaging Permission -->
 <div>
 <h4 class="text-sm font-medium text-gray-900">Direct Messages</h4>
 <p class="text-sm text-gray-500 mb-4">Control who can send you direct messages.</p>

 <div class="space-y-4">
 <div class="flex items-center">
 <input id="mp_everyone" name="messaging_permission" type="radio" value="everyone"
 class="h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-500"
 @checked(old('messaging_permission', $user->messaging_permission) ==='everyone')>
 <label for="mp_everyone" class="ml-3 block text-sm font-medium leading-6 text-gray-900">
 Everyone
 </label>
 </div>
 <div class="flex items-center">
 <input id="mp_followers" name="messaging_permission" type="radio" value="followers_only"
 class="h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-500"
 @checked(old('messaging_permission', $user->messaging_permission) ==='followers_only')>
 <label for="mp_followers" class="ml-3 block text-sm font-medium leading-6 text-gray-900">
 Followers Only
 </label>
 </div>
 </div>
 <x-input-error class="mt-2" :messages="$errors->get(' messaging_permission')"/>
 </div>

 <hr class="border-gray-200">

 <!-- Pets Visibility -->
 <div>
 <h4 class="text-sm font-medium text-gray-900">Pets Visibility</h4>
 <p class="text-sm text-gray-500 mb-4">Control who can see the pets associated with your account.</p>

 <div class="space-y-4">
 <div class="flex items-center">
 <input id="pet_everyone" name="pets_visibility" type="radio" value="everyone"
 class="h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-500"
 @checked(old('pets_visibility', $user->pets_visibility) ==='everyone')>
 <label for="pet_everyone" class="ml-3 block text-sm font-medium leading-6 text-gray-900">
 Everyone
 </label>
 </div>
 <div class="flex items-center">
 <input id="pet_followers" name="pets_visibility" type="radio" value="followers_only"
 class="h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-500"
 @checked(old('pets_visibility', $user->pets_visibility) ==='followers_only')>
 <label for="pet_followers" class="ml-3 block text-sm font-medium leading-6 text-gray-900">
 Followers Only
 </label>
 </div>
 </div>
 <x-input-error class="mt-2" :messages="$errors->get(' pets_visibility')"/>
 </div>

 <hr class="border-gray-200">

 <!-- Groups Visibility -->
 <div>
 <h4 class="text-sm font-medium text-gray-900">Groups Visibility</h4>
 <p class="text-sm text-gray-500 mb-4">Control who can see the groups you have joined.</p>

 <div class="space-y-4">
 <div class="flex items-center">
 <input id="grp_everyone" name="groups_visibility" type="radio" value="everyone"
 class="h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-500"
 @checked(old('groups_visibility', $user->groups_visibility) ==='everyone')>
 <label for="grp_everyone" class="ml-3 block text-sm font-medium leading-6 text-gray-900">
 Everyone
 </label>
 </div>
 <div class="flex items-center">
 <input id="grp_followers" name="groups_visibility" type="radio" value="followers_only"
 class="h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-500"
 @checked(old('groups_visibility', $user->groups_visibility) ==='followers_only')>
 <label for="grp_followers" class="ml-3 block text-sm font-medium leading-6 text-gray-900">
 Followers Only
 </label>
 </div>
 </div>
 <x-input-error class="mt-2" :messages="$errors->get(' groups_visibility')"/>
 </div>

 <hr class="border-gray-200">

 <!-- Toggles -->
 <div class="space-y-6">
 <x-ui.toggle name="show_in_explore" label="Show in Explore"
 description="Allow your profile to be recommended to other users."
 :checked="old('show_in_explore', $user->show_in_explore)"/>

 <x-ui.toggle name="open_following" label="Open Following"
 description="Allow anyone to see who you follow." :checked="old('open_following', $user->open_following)"/>
 </div>

 </div>

 <div class="flex justify-end border-t border-gray-200 pt-5">
 <x-primary-button>Save Privacy Settings</x-primary-button>
 </div>
 </form>
 </div>
</x-settings-layout>