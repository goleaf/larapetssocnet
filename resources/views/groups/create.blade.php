<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Create Group</h2>
            <a href="{{ route('groups.index') }}" class="btn-base btn-ghost px-3 py-2 text-sm">Back to Groups</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('groups.store') }}" class="shell-card space-y-6 p-6">
                @csrf

                @include('groups._form', [
                    'group' => $group,
                    'selectedPrivacy' => $selectedPrivacy,
                ])

                <div class="flex items-center justify-end gap-2">
                    <a href="{{ route('groups.index') }}" class="btn-base btn-ghost">Cancel</a>
                    <button type="submit" class="btn-base btn-primary">Create Group</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
