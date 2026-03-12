@section('title', ($pet->name ?? 'Pet').' — Followers')

<x-app-layout>
 <x-slot name="header">
 <div class="flex flex-wrap items-center justify-between gap-3">
 <div>
 <h1 class="shell-title text-xl">Followers</h1>
 <p class="mt-1 text-sm shell-text-muted">{{ $pet->name }} · {{ number_format((int) ($pet->followers_count ?? 0)) }} followers</p>
 </div>
 <a href="{{ route('pets.show', $pet) }}" class="text-sm text-indigo-600 hover:text-indigo-800">Back to profile</a>
 </div>
 </x-slot>

 <section class="shell-card p-5">
 <div class="space-y-2">
 @forelse ($followers as $follower)
 <article class="flex items-center gap-3 rounded-xl p-3 hover:bg-slate-50">
 <a href="{{ route('profile.show', ['user' => $follower]) }}">
 <x-avatar :user="$follower" size="md"/>
 </a>

 <div class="min-w-0 flex-1">
 <a href="{{ route('profile.show', ['user' => $follower]) }}"
 class="truncate font-semibold hover:underline">{{ $follower->name }}</a>
 <p class="text-xs shell-text-muted">&#64;{{ $follower->username }}</p>
 </div>
 </article>
 @empty
 <x-empty-state icon="users" title="No followers yet" description="Followers will appear here."/>
 @endforelse
 </div>

 <div class="mt-4">
 {{ $followers->links() }}
 </div>
 </section>
</x-app-layout>
