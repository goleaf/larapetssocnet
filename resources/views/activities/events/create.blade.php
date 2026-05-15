<x-app-layout>
 <x-slot name="header">
 <x-ui.page-header title="Create Event" description="Plan a new community event and invite attendees." icon="🗓️">
 <x-slot name="action">
 <a href="{{ route('events.index') }}" class="btn-base btn-ghost px-3 py-2 text-sm">Back to Events</a>
 </x-slot>
 </x-ui.page-header>
 </x-slot>

 <div class="py-8">
 <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
 <form method="POST" action="{{ route('events.store') }}" class="shell-card space-y-6 p-6">
 @csrf

 @include('activities.events._form', [
'event'=> $event,
'groups'=> $groups,
'selectedGroupId'=> $selectedGroupId,
'locationColumn'=> $locationColumn,
'maxAttendeesColumn'=> $maxAttendeesColumn,
 ])

 <div class="flex items-center justify-end gap-2">
 <a href="{{ route('events.index') }}" class="btn-base btn-ghost">Cancel</a>
 <button type="submit" class="btn-base btn-primary">Create Event</button>
 </div>
 </form>
 </div>
 </div>
</x-app-layout>
