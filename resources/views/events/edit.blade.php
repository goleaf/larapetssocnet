<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Edit Event</h2>
            <a href="{{ route('events.show', $event->id) }}" class="btn-base btn-ghost px-3 py-2 text-sm">Back to Event</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl space-y-4 px-4 sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('events.update', $event->id) }}" class="shell-card space-y-6 p-6">
                @csrf
                @method('PATCH')

                @include('events._form', [
                    'event' => $event,
                    'groups' => $groups,
                    'selectedGroupId' => $selectedGroupId,
                    'startAt' => $startAt,
                    'endAt' => $endAt,
                    'locationColumn' => $locationColumn,
                    'maxAttendeesColumn' => $maxAttendeesColumn,
                ])

                <div class="flex items-center justify-end gap-2">
                    <a href="{{ route('events.show', $event->id) }}" class="btn-base btn-ghost">Cancel</a>
                    <button type="submit" class="btn-base btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
