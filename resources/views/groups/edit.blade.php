<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Edit Group</h2>
            <a href="{{ route('groups.show', $groupRouteKey = ($group->slug ?? $group->id)) }}" class="btn-base btn-ghost px-3 py-2 text-sm">Back to Group</a>
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

                <div class="flex items-center justify-between gap-2">
                    <div>
                        <form method="POST" action="{{ route('groups.destroy', $groupRouteKey) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-base btn-danger" onclick="return confirm('Delete this group?')">Delete Group</button>
                        </form>
                    </div>
                    <div class="flex items-center gap-2">
                        <a href="{{ route('groups.show', $groupRouteKey) }}" class="btn-base btn-ghost">Cancel</a>
                        <button type="submit" class="btn-base btn-primary">Save Changes</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
