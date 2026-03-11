<x-app-layout>
 <x-slot name="header">
 <div class="flex items-center justify-between gap-3">
 <h2 class="font-semibold text-xl text-gray-400 leading-tight">Create Event</h2>
 <a href="{{ route('events.index') }}" class="btn-base btn-ghost px-3 py-2 text-sm">Back to Events</a>
 </div>
 </x-slot>

 <div class="py-8">
 <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
 <form method="POST" action="{{ route('events.store') }}" class="shell-card space-y-6 p-6">
 @csrf

 @include('events._form', [
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
