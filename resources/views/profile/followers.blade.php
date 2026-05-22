@section('title','@'. $user->username .'— Followers')

<x-app-layout>
 <x-slot name="header">
 <x-ui.page-header
 :title="($showMutualOnly ?? false) ? 'Mutual connections' : 'Followers'"
 :description="'@'.$user->username.' · '.number_format((int) $user->followers_count).' followers'"
 icon="👥"
 />
 </x-slot>

 <section class="shell-card p-5">
 <form method="GET" class="mb-4">
 @if ($showMutualOnly ?? false)
 <input type="hidden" name="mutual" value="1">
 @endif
 <input type="search" name="q" value="{{ request('q') }}" placeholder="{{ ($showMutualOnly ?? false) ? 'Search mutual connections...' : 'Search followers...' }}"
 class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
 </form>

 <div class="space-y-2">
 @forelse ($followers as $follower)
 <article data-user-card class="flex items-center gap-3 rounded-xl p-3 hover:bg-slate-50">
 <a href="{{ route('profile.show', ['user'=> $follower]) }}">
 <x-avatar :user="$follower" size="md"/>
 </a>

 <div class="min-w-0 flex-1">
 <a href="{{ route('profile.show', ['user'=> $follower]) }}"
 class="truncate font-semibold hover:underline">{{ $follower->name }}</a>
 <p class="text-xs shell-text-muted">&#64;{{ $follower->username }}</p>
 </div>

	 @auth
	 @if (auth()->id() !== $follower->id)
	 <x-follow-button :user="$follower" :follow-status="$followStatusMap[$follower->id] ?? 'none'" size="sm"
	 :show-remove="auth()->id() === $user->id"/>
	 @endif
	 @endauth
 </article>
 @empty
 <x-ui.empty-state icon="users" :title="($showMutualOnly ?? false) ? 'No mutual connections yet' : 'No followers yet'" :description="($showMutualOnly ?? false) ? 'People you follow who also follow this profile will appear here.' : 'Followers will appear here.'"/>
 @endforelse
 </div>

 <div class="mt-4">
 {{ $followers->appends(request()->query())->links() }}
 </div>
 </section>
</x-app-layout>
