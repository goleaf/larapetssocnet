<x-app-layout>
 @php
 $groupRouteKey = filled((string) ($group->slug ??'')) ? $group->slug : $group->id;
 $privacyValue = old('privacy', $selectedPrivacy ??'public');
 $speciesValue = old('species', data_get($group,'species','all_pets'));

 $privacyOptions = [
 ['value'=>'public','label'=>'Public','description'=>'Anyone can discover and join instantly.'],
 ['value'=>'private','label'=>'Private','description'=>'Visible in search, but new members need approval.'],
 ['value'=>'secret','label'=>'Secret','description'=>'Hidden from discovery and joinable by invite only.'],
 ];

 $speciesOptions = [
 ['value'=>'all_pets','label'=>'All Pets'],
 ['value'=>'dogs','label'=>'Dogs'],
 ['value'=>'cats','label'=>'Cats'],
 ['value'=>'birds','label'=>'Birds'],
 ['value'=>'small_pets','label'=>'Small Pets'],
 ['value'=>'reptiles','label'=>'Reptiles'],
 ['value'=>'aquatic','label'=>'Aquatic'],
 ['value'=>'mixed','label'=>'Mixed'],
 ];
 @endphp

 <x-slot name="header">
 <x-ui.page-header
 title="Edit Group"
 subtitle="Manage Community"
 :breadcrumbs="[
 ['label'=>'Groups','href'=> route('groups.index')],
 ['label'=> $group->name,'href'=> route('groups.show', $groupRouteKey)],
 ['label'=>'Edit'],
 ]"
 >
 <x-slot name="action">
 <x-ui.button href="{{ route('groups.show', $groupRouteKey) }}"variant="ghost"size="sm">Back to Group</x-ui.button>
 </x-slot>
 </x-ui.page-header>
 </x-slot>

 <x-ui.card>
 <form method="POST"action="{{ route('groups.update', $groupRouteKey) }}"enctype="multipart/form-data"class="space-y-6">
 @csrf
 @method('PATCH')

 <x-ui.form-section title="Basics"description="Set the group identity and focus.">
 <div class="grid gap-4 md:grid-cols-2">
 <x-ui.input
 class="md:col-span-2"
 name="name"
 label="Group Name"
 required
 maxlength="160"
 :value="old('name', $group->name ??'')"
 />

 <x-ui.select
 class="md:col-span-2"
 name="species"
 label="Species Focus"
 :options="$speciesOptions"
 :selected="$speciesValue"
 />
 </div>
 </x-ui.form-section>

 <x-ui.form-section title="Privacy"description="Choose how members discover and join.">
 <x-ui.radio-group
 name="privacy"
 label="Group Type"
 :options="$privacyOptions"
 :selected="$privacyValue"
 />
 </x-ui.form-section>

 <x-ui.form-section title="Content"description="Explain what this community is about.">
 <div class="space-y-4">
 <x-ui.textarea
 name="description"
 label="Description"
 rows="5"
 maxlength="5000"
 :value="old('description', $group->description ??'')"
 />

 <x-ui.textarea
 name="rules"
 label="Group Rules"
 rows="4"
 maxlength="5000"
 :value="old('rules', $group->rules ??'')"
 />
 </div>
 </x-ui.form-section>

 <x-ui.form-section title="Media"description="Update avatar, cover, or fallback URL.">
 <div class="grid gap-4 md:grid-cols-2">
 <x-ui.file-upload
 name="avatar"
 label="Avatar"
 accept="image/*"
 preview
 max-size="5MB"
 hint="Leave empty to keep existing avatar."
 :error="$errors->first('avatar')"
 />

 <x-ui.file-upload
 name="cover"
 label="Cover"
 accept="image/*"
 preview
 max-size="8MB"
 hint="Leave empty to keep existing cover."
 :error="$errors->first('cover')"
 />
 </div>

 <x-ui.input
 name="cover_image_path"
 label="Cover URL (optional fallback)"
 type="url"
 :value="old('cover_image_path', $group->cover_image_path ??'')"
 placeholder="https://example.com/cover.jpg"
 />
 </x-ui.form-section>

 <div class="flex flex-wrap items-center justify-between gap-3">
 @if (!empty($canDelete))
 <form method="POST"action="{{ route('groups.destroy', $groupRouteKey) }}"onsubmit="return confirm('Delete this group?');">
 @csrf
 @method('DELETE')
 <x-ui.button type="submit"variant="danger">Delete Group</x-ui.button>
 </form>
 @else
 <span></span>
 @endif

 <div class="flex items-center gap-2">
 <x-ui.button href="{{ route('groups.show', $groupRouteKey) }}"variant="ghost">Cancel</x-ui.button>
 <x-ui.button type="submit"variant="primary">Save Changes</x-ui.button>
 </div>
 </div>
 </form>
 </x-ui.card>
</x-app-layout>
