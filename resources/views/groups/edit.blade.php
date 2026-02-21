<x-app-layout>
    @php
        $groupRouteKey = filled((string) ($group->slug ?? '')) ? $group->slug : $group->id;
    @endphp

    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Edit Group</h2>
            <a href="{{ route('groups.show', $groupRouteKey) }}" class="btn-base btn-ghost px-3 py-2 text-sm">Back to Group</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('groups.update', $groupRouteKey) }}" class="shell-card space-y-6 p-6">
                @csrf
                @method('PATCH')

                @include('groups._form', [
                    'group' => $group,
                    'selectedPrivacy' => $selectedPrivacy,
                ])

                <div class="flex items-center justify-end gap-2">
                    <a href="{{ route('groups.show', $groupRouteKey) }}" class="btn-base btn-ghost">Cancel</a>
                    <button type="submit" class="btn-base btn-primary">Save Changes</button>
                </div>
            </form>

            @if (! empty($canDelete))
                <form method="POST" action="{{ route('groups.destroy', $groupRouteKey) }}" class="mt-3">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn-base btn-danger" onclick="return confirm('Delete this group?')">Delete Group</button>
                </form>
            @endif
        </div>
    </div>
</x-app-layout>
