<x-settings-layout>
 <div class="space-y-6">
 <div>
 <h3 class="text-lg font-semibold text-bark">Blocked Users</h3>
 <p class="mt-1 text-sm text-fur">When you block someone, they cannot view your profile, contact you, or see your posts.</p>
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

 <x-ui.button type="submit" variant="danger" class="sm:shrink-0">Block User</x-ui.button>
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
 <img
 class="h-10 w-10 rounded-full border border-whisker/30 object-cover"
 src="{{ $blockedUser->avatar_url ?? 'https://ui-avatars.com/api/?name='.urlencode($blockedUser->name).'&color=7F9CF5&background=EBF4FF' }}"
 alt="{{ $blockedUser->name }}"
 >
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
 <x-ui.button type="submit" variant="ghost" size="sm">Unblock<span class="sr-only"> {{ $blockedUser->name }}</span></x-ui.button>
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
