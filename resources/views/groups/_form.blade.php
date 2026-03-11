@props([
'group',
'selectedPrivacy'=>'public',
])

@php
 $privacyValue = old('privacy', $selectedPrivacy ?:'public');
@endphp

<div class="space-y-5">
 <div>
 <x-input-label for="name" :value="' Group Name'"/>
 <x-text-input
 id="name"
 name="name"
 type="text"
 class="mt-1 block w-full"
 :value="old('name', $group->name ??'')"
 required
 maxlength="160"
 />
 <x-input-error :messages="$errors->get(' name')" class="mt-2"/>
 </div>

 <div>
 <x-input-label for="description" :value="' Description'"/>
 <textarea
 id="description"
 name="description"
 rows="4"
 class="mt-1 block w-full rounded-md border border-[var(--ui-border)] bg-white px-3 py-2 text-sm focus:border-[var(--ui-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/25"
 >{{ old('description', $group->description ??'') }}</textarea>
 <x-input-error :messages="$errors->get(' description')" class="mt-2"/>
 </div>

 <div>
 <x-input-label for="privacy" :value="' Group Type'"/>
 <select
 id="privacy"
 name="privacy"
 class="mt-1 block w-full rounded-md border border-[var(--ui-border)] bg-white px-3 py-2 text-sm focus:border-[var(--ui-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/25"
 >
 <option value="public"@selected($privacyValue ==='public')>Public (anyone can join)</option>
 <option value="private"@selected($privacyValue ==='private')>Private (join requests)</option>
 <option value="secret"@selected($privacyValue ==='secret')>Secret (hidden group)</option>
 </select>
 <x-input-error :messages="$errors->get(' privacy')" class="mt-2"/>
 </div>

 <div>
 <x-input-label for="rules" :value="' Group Rules'"/>
 <textarea
 id="rules"
 name="rules"
 rows="4"
 class="mt-1 block w-full rounded-md border border-[var(--ui-border)] bg-white px-3 py-2 text-sm focus:border-[var(--ui-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/25"
 >{{ old('rules', $group->rules ??'') }}</textarea>
 <x-input-error :messages="$errors->get(' rules')" class="mt-2"/>
 </div>

 <div>
 <x-input-label for="cover_image_path" :value="' Cover Image URL (optional)'"/>
 <x-text-input
 id="cover_image_path"
 name="cover_image_path"
 type="text"
 class="mt-1 block w-full"
 :value="old('cover_image_path', $group->cover_image_path ?? $group->cover_photo_path ??'')"
 maxlength="2048"
 />
 <x-input-error :messages="$errors->get(' cover_image_path')" class="mt-2"/>
 </div>
</div>
