@props([
'event',
'groups'=> collect(),
'selectedGroupId'=> null,
'startAt'=> null,
'endAt'=> null,
'locationColumn'=>'location',
'maxAttendeesColumn'=> null,
])

@php
 $startValue = old('starts_at', $startAt ? $startAt->format('Y-m-d\TH:i') : now()->addDay()->format('Y-m-d\TH:i'));
 $endValue = old('ends_at', $endAt ? $endAt->format('Y-m-d\TH:i') :'');
 $locationValue = old('location', data_get($event, $locationColumn) ??'');
 $maxAttendeesValue = old('max_attendees', $maxAttendeesColumn ? (data_get($event, $maxAttendeesColumn) ??'') :'');
 $groupValue = old('group_id', $selectedGroupId ?? data_get($event,'group_id'));
@endphp

<div class="space-y-5">
 <div>
 <x-input-label for="title":value="'Event Title'"/>
 <x-text-input
 id="title"
 name="title"
 type="text"
 class="mt-1 block w-full"
 :value="old('title', $event->title ??'')"
 maxlength="180"
 required
 />
 <x-input-error :messages="$errors->get('title')"class="mt-2"/>
 </div>

 <div>
 <x-input-label for="description":value="'Description'"/>
 <textarea
 id="description"
 name="description"
 rows="4"
 class="mt-1 block w-full rounded-md border border-[var(--ui-border)] bg-white px-3 py-2 text-sm focus:border-[var(--ui-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/25"
 >{{ old('description', $event->description ??'') }}</textarea>
 <x-input-error :messages="$errors->get('description')"class="mt-2"/>
 </div>

 <div>
 <x-input-label for="group_id":value="'Group (optional)'"/>
 <select
 id="group_id"
 name="group_id"
 class="mt-1 block w-full rounded-md border border-[var(--ui-border)] bg-white px-3 py-2 text-sm"
 >
 <option value="">No group</option>
 @foreach ($groups as $group)
 <option value="{{ $group->id }}"@selected((string) $groupValue === (string) $group->id)>
 {{ $group->name }}
 </option>
 @endforeach
 </select>
 <x-input-error :messages="$errors->get('group_id')"class="mt-2"/>
 </div>

 <div>
 <x-input-label for="location":value="'Location'"/>
 <x-text-input
 id="location"
 name="location"
 type="text"
 class="mt-1 block w-full"
 :value="$locationValue"
 maxlength="255"
 placeholder="Park name, online URL, or address"
 />
 <x-input-error :messages="$errors->get('location')"class="mt-2"/>
 </div>

 <div class="grid gap-4 md:grid-cols-2">
 <div>
 <x-input-label for="starts_at":value="'Starts At'"/>
 <x-text-input id="starts_at"name="starts_at"type="datetime-local"class="mt-1 block w-full":value="$startValue"required />
 <x-input-error :messages="$errors->get('starts_at')"class="mt-2"/>
 </div>

 <div>
 <x-input-label for="ends_at":value="'Ends At (optional)'"/>
 <x-text-input id="ends_at"name="ends_at"type="datetime-local"class="mt-1 block w-full":value="$endValue"/>
 <x-input-error :messages="$errors->get('ends_at')"class="mt-2"/>
 </div>
 </div>

 <div>
 <x-input-label for="max_attendees":value="'Max Attendees (optional)'"/>
 <x-text-input
 id="max_attendees"
 name="max_attendees"
 type="number"
 class="mt-1 block w-full"
 :value="$maxAttendeesValue"
 min="1"
 placeholder="Leave empty for unlimited"
 />
 <x-input-error :messages="$errors->get('max_attendees')"class="mt-2"/>
 </div>
</div>
