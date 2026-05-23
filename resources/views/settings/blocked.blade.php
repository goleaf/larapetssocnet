<x-settings-layout>
 <div class="space-y-6" data-ui="settings-blocked-page">
 <div class="space-y-2" data-ui="settings-page-header">
 <p class="chip min-h-8">Safety controls</p>
 <h2 class="shell-title text-2xl">Blocked Users</h2>
 <p class="max-w-2xl text-sm leading-6 shell-text-muted">When you block someone, they cannot view your profile, contact you, or see your posts.</p>
 </div>

 <x-ui.card padding="md" class="bg-cream/40">
 <form action="{{ route('settings.blocked.store') }}" method="POST" class="flex flex-col gap-3 sm:flex-row sm:items-end">
 @csrf
 <div class="min-w-0 flex-1">
 <x-ui.input
 id="username"
 name="username"
 label="Username"
 placeholder="Enter username to block"
 />
</div>

 <x-ui.button type="submit" variant="danger" class="min-h-11 sm:shrink-0">Block User</x-ui.button>
 </form>
 </x-ui.card>

 @if($blockedUsers->isEmpty())
 <x-ui.empty-state icon="🚫" title="No blocked users" description="You have not blocked anyone yet." />
 @else
 <x-ui.table :headings="['User', 'Date Blocked', 'Actions']">
 @foreach($blockedUsers as $blockedUser)
 <x-ui.table-row>
 <x-ui.table-cell>
 <div class="flex items-center gap-3">
 <x-ui.avatar :src="$blockedUser->avatar_url" :name="$blockedUser->name" :user="$blockedUser" size="md"/>
 <div class="min-w-0">
 <p class="truncate text-sm font-semibold text-bark">{{ $blockedUser->name }}</p>
 <p class="truncate text-xs text-fur">{{ '@'.$blockedUser->username }}</p>
 </div>
 </div>
 </x-ui.table-cell>

 <x-ui.table-cell>
 <span class="text-sm text-fur">{{ $blockedUser->pivot->created_at->format('M j, Y') }}</span>
 </x-ui.table-cell>

 <x-ui.table-cell align="right">
 <form action="{{ route('settings.blocked.destroy', $blockedUser->username) }}" method="POST" class="inline">
 @csrf
 @method('DELETE')
 <x-ui.button type="submit" variant="ghost" size="sm" class="min-h-11">Unblock<span class="sr-only"> {{ $blockedUser->name }}</span></x-ui.button>
 </form>
 </x-ui.table-cell>
 </x-ui.table-row>
 @endforeach
 </x-ui.table>

 <div class="mt-4">
 {{ $blockedUsers->links() }}
 </div>
 @endif
 </div>
</x-settings-layout>
