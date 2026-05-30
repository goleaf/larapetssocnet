<x-settings-layout>
    <div class="space-y-6" data-ui="settings-muted-page">
        <div class="space-y-2" data-ui="settings-page-header">
            <p class="chip min-h-8">Feed controls</p>
            <h2 class="shell-title text-2xl">Muted Accounts</h2>
            <p class="max-w-2xl text-sm leading-6 shell-text-muted">Muted people and pets stay followed, but their posts no longer appear in your main feed.</p>
        </div>

        @if ($mutedAccounts->isEmpty())
            <x-ui.empty-state icon="🔇" title="No muted accounts" description="Mute a person or pet from a post menu when you want a quieter feed without unfollowing." />
        @else
            <x-ui.table :headings="['Muted source', 'Type', 'Muted on', 'Actions']">
                @foreach ($mutedAccounts as $mute)
                    @php
                        $target = $mute->mutable;
                        $isUser = $target instanceof \App\Models\Identity\User;
                        $isPet = $target instanceof \App\Models\Pets\Pet;
                        $label = $isUser ? ($target->name ?? 'Unavailable user') : ($isPet ? ($target->name ?? 'Unavailable pet') : 'Unavailable source');
                        $subLabel = $isUser ? '@'.($target->username ?? 'unknown') : ($isPet ? collect([$target->species, $target->breed])->filter()->join(' · ') : 'This source is no longer available.');
                    @endphp

                    <x-ui.table-row>
                        <x-ui.table-cell>
                            <div class="flex min-w-0 items-center gap-3">
                                @if ($isUser)
                                    <x-ui.avatar :src="$target->avatar_url" :name="$target->name" :user="$target" size="md"/>
                                @elseif ($isPet)
                                    <x-ui.avatar :src="$target->avatar_url" :name="$target->name" size="md"/>
                                @else
                                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-cream text-sm font-bold text-fur">?</span>
                                @endif

                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-bark">{{ $label }}</p>
                                    <p class="truncate text-xs text-fur">{{ $subLabel }}</p>
                                </div>
                            </div>
                        </x-ui.table-cell>
                        <x-ui.table-cell>
                            <x-ui.badge size="sm">{{ $isPet ? 'Pet' : 'Person' }}</x-ui.badge>
                        </x-ui.table-cell>
                        <x-ui.table-cell>
                            <span class="text-sm text-fur">{{ $mute->created_at?->format('M j, Y') }}</span>
                        </x-ui.table-cell>
                        <x-ui.table-cell align="right">
                            <form action="{{ route('feed.mutes.destroy', $mute) }}" method="POST" class="inline">
                                @csrf
                                @method('DELETE')
                                <x-ui.button type="submit" variant="ghost" size="sm" class="min-h-11">Unmute<span class="sr-only"> {{ $label }}</span></x-ui.button>
                            </form>
                        </x-ui.table-cell>
                    </x-ui.table-row>
                @endforeach
            </x-ui.table>

            <div class="mt-4">
                {{ $mutedAccounts->links() }}
            </div>
        @endif
    </div>
</x-settings-layout>
