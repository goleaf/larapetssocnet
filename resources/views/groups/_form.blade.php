@props([
'group',
'selectedPrivacy'=>'public',
])

<div class="space-y-5">
 <div>
 <x-ui.input
 id="name"
 name="name"
 type="text"
 label="Group Name"
 :value="old('name', $group->name ?? '')"
 required
 maxlength="160"
 />
 </div>

 <div>
 <x-ui.textarea id="description" name="description" rows="4" label="Description" :value="old('description', $group->description ?? '')"/>
 </div>

 <div>
 <x-ui.select
 id="privacy"
 name="privacy"
 label="Group Type"
 :options="[
 'public' => 'Public (anyone can join)',
 'private' => 'Private (join requests)',
 'secret' => 'Secret (hidden group)',
 ]"
 :selected="old('privacy', $selectedPrivacy ?: 'public')"
 />
 </div>

 <div>
 <x-ui.textarea id="rules" name="rules" rows="4" label="Group Rules" :value="old('rules', $group->rules ?? '')"/>
 </div>

 <div>
 <x-ui.input
 id="cover_image_path"
 name="cover_image_path"
 type="text"
 label="Cover Image URL (optional)"
 :value="old('cover_image_path', $group->cover_image_path ?? $group->cover_photo_path ?? '')"
 maxlength="2048"
 />
 </div>
</div>
