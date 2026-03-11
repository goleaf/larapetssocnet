@php
 $suggestions = auth()->check() ? auth()->user()->getSuggestedUsersToFollow(4) : collect();
@endphp

@if ($suggestions->count())
 <aside class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
 <h3 class="mb-3 text-sm font-semibold text-gray-400">{{ __('en.who_to_follow') }}</h3>

 <div class="space-y-3">
 @foreach ($suggestions as $suggested)
 <div class="flex items-center gap-2.5">
 <a href="{{ route('profile.show', ['user'=> $suggested]) }}" class="flex-shrink-0">
 <x-avatar :user="$suggested" size="sm" />
 </a>
 <div class="min-w-0 flex-1">
 <a href="{{ route('profile.show', ['user'=> $suggested]) }}" class="block truncate text-xs font-medium text-gray-400 hover:underline">{{ $suggested->name }}</a>
 <p class="truncate text-xs text-gray-400">&#64;{{ $suggested->username }}</p>
 </div>
 <x-follow-button :user="$suggested" follow-status="none" size="sm" />
 </div>
 @endforeach
 </div>

 <a href="{{ route('explore.index') }}" class="mt-3 block text-center text-xs text-emerald-600 hover:underline">
 {{ __('en.see_more_suggestions') }}
 </a>
 </aside>
@endif
