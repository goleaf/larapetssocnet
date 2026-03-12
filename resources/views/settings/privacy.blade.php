<x-settings-layout>
 <div class="space-y-6">
 <div>
 <h3 class="text-lg font-medium leading-6 text-bark">Privacy & Visibility</h3>
 <p class="mt-1 text-sm text-fur">Manage who can see your content and who can interact with you.</p>
 </div>

 <form action="{{ route('settings.privacy.update') }}" method="POST" class="space-y-8">
 @csrf
 @method('PUT')

 <div class="space-y-6">
 <!-- Profile Visibility -->
 <div>
 <h4 class="text-sm font-medium text-bark">Profile Visibility</h4>
 <p class="text-sm text-fur mb-4">Control who can view your posts and personal details.</p>

 <x-ui.radio-group
 name="profile_visibility"
 :selected="old('profile_visibility', $user->profile_visibility)"
 :options="[
 ['value' => 'public', 'label' => 'Public', 'description' => 'Anyone can see your profile and posts'],
 ['value' => 'followers_only', 'label' => 'Followers Only', 'description' => 'Only approved followers can see your profile and posts'],
 ['value' => 'private', 'label' => 'Private', 'description' => 'Only you can see your profile'],
 ]"
 />
 </div>

 <hr class="border-whisker/30">

 <!-- Messaging Permission -->
 <div>
 <h4 class="text-sm font-medium text-bark">Direct Messages</h4>
 <p class="text-sm text-fur mb-4">Control who can send you direct messages.</p>

 <x-ui.radio-group
 name="messaging_permission"
 :selected="old('messaging_permission', $user->messaging_permission)"
 :options="[
 ['value' => 'everyone', 'label' => 'Everyone'],
 ['value' => 'followers_only', 'label' => 'Followers Only'],
 ]"
 />
 </div>

 <hr class="border-whisker/30">

 <!-- Pets Visibility -->
 <div>
 <h4 class="text-sm font-medium text-bark">Pets Visibility</h4>
 <p class="text-sm text-fur mb-4">Control who can see the pets associated with your account.</p>

 <x-ui.radio-group
 name="pets_visibility"
 :selected="old('pets_visibility', $user->pets_visibility)"
 :options="[
 ['value' => 'everyone', 'label' => 'Everyone'],
 ['value' => 'followers_only', 'label' => 'Followers Only'],
 ]"
 />
 </div>

 <hr class="border-whisker/30">

 <!-- Groups Visibility -->
 <div>
 <h4 class="text-sm font-medium text-bark">Groups Visibility</h4>
 <p class="text-sm text-fur mb-4">Control who can see the groups you have joined.</p>

 <x-ui.radio-group
 name="groups_visibility"
 :selected="old('groups_visibility', $user->groups_visibility)"
 :options="[
 ['value' => 'everyone', 'label' => 'Everyone'],
 ['value' => 'followers_only', 'label' => 'Followers Only'],
 ]"
 />
 </div>

 <hr class="border-whisker/30">

 <!-- Toggles -->
 <div class="space-y-6">
 <x-ui.toggle name="show_in_explore" label="Show in Explore"
 description="Allow your profile to be recommended to other users."
 :checked="old('show_in_explore', $user->show_in_explore)"/>

 <x-ui.toggle name="open_following" label="Open Following"
 description="Allow anyone to see who you follow." :checked="old('open_following', $user->open_following)"/>
 </div>

 </div>

 <div class="flex justify-end border-t border-whisker/30 pt-5">
 <x-ui.button variant="primary">Save Privacy Settings</x-ui.button>
 </div>
 </form>
 </div>
</x-settings-layout>
