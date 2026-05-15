@props([
'event',
'groups'=> collect(),
'selectedGroupId'=> null,
'startAt'=> null,
'endAt'=> null,
'locationColumn'=>'location',
'maxAttendeesColumn'=> null,
])

<div class="space-y-5">
 <div>
 <x-ui.input
 id="title"
 name="title"
 type="text"
 label="Event Title"
 :value="old('title', $event->title ?? '')"
 maxlength="180"
 required
 />
 </div>

 <div>
 <x-ui.textarea id="description" name="description" rows="4" label="Description" :value="old('description', $event->description ?? '')"/>
 </div>

 <div>
 <x-ui.select
 id="group_id"
 name="group_id"
 label="Group (optional)"
 :options="collect(['' => 'No group'])->merge($groups->mapWithKeys(fn ($group) => [$group->id => $group->name]))->all()"
 :selected="old('group_id', $selectedGroupId ?? data_get($event, 'group_id'))"
 />
 </div>

 <div>
 <x-ui.input
 id="location"
 name="location"
 type="text"
 label="Location"
 :value="old('location', data_get($event, $locationColumn) ?? '')"
 maxlength="255"
 placeholder="Park name, online URL, or address"
 />
 </div>

 <div class="grid gap-4 md:grid-cols-2">
 <div>
 <x-ui.input id="starts_at" name="starts_at" type="datetime-local" label="Starts At"
 :value="old('starts_at', $startAt ? $startAt->format('Y-m-d\\TH:i') : now()->addDay()->format('Y-m-d\\TH:i'))" required/>
 </div>

 <div>
 <x-ui.input id="ends_at" name="ends_at" type="datetime-local" label="Ends At (optional)"
 :value="old('ends_at', $endAt ? $endAt->format('Y-m-d\\TH:i') : '')"/>
 </div>
 </div>

 <div>
 <x-ui.input
 id="max_attendees"
 name="max_attendees"
 type="number"
 label="Max Attendees (optional)"
 :value="old('max_attendees', $maxAttendeesColumn ? (data_get($event, $maxAttendeesColumn) ?? '') : '')"
 min="1"
 placeholder="Leave empty for unlimited"
 />
 </div>
</div>
