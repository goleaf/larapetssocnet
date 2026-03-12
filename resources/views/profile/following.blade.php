@section('title','@'. $user->username .'— Following')

<x-app-layout>
 <x-slot name="header">
 <x-ui.page-header title="Following" :description="'@'.$user->username.' · '.number_format((int) $user->following_count).' following'" icon="🧑‍🤝‍🧑" />
 </x-slot>

 <section class="shell-card p-5">
 <form method="GET" class="mb-4">
 <input type="search" name="q" value="{{ request('q') }}" placeholder="Search following..."
 class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
 </form>

 <div class="space-y-2">
 @forelse ($following as $followedUser)
 <article class="flex items-center gap-3 rounded-xl p-3 hover:bg-slate-50">
 <a href="{{ route('profile.show', ['user'=> $followedUser]) }}">
 <x-avatar :user="$followedUser" size="md"/>
 </a>

 <div class="min-w-0 flex-1">
 <a href="{{ route('profile.show', ['user'=> $followedUser]) }}"
 class="truncate font-semibold hover:underline">{{ $followedUser->name }}</a>
 <p class="text-xs shell-text-muted">&#64;{{ $followedUser->username }}</p>
 @if (in_array($followedUser->id, $followsYouIds ?? [], true))
 <span class="mt-1 inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">Follows
 you</span>
 @endif
 </div>

	 @auth
	 @if (auth()->id() !== $followedUser->id)
	 <x-follow-button :user="$followedUser" :follow-status="$followStatusMap[$followedUser->id] ?? 'none'"
	 size="sm"/>
	 @endif
	 @endauth
 </article>
 @empty
 <x-ui.empty-state icon="user-plus" title="Not following anyone yet"
 description="Profiles followed by this user will appear here."/>
 @endforelse
 </div>

 <div class="mt-4">
 {{ $following->appends(request()->query())->links() }}
 </div>
 </section>
</x-app-layout>
