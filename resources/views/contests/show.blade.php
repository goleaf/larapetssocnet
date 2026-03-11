@extends('layouts.app')
@section('title', $contest->title)

@section('content')
 <div class="max-w-4xl mx-auto">
 <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
 <div class="flex justify-between items-start mb-4">
 <div>
 <h1 class="text-2xl font-bold text-gray-400">{{ $contest->title }}</h1>
 <p class="text-sm text-gray-400 mt-1">by {{ $contest->organizer->name ??'Unknown'}}</p>
 </div>
 <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium
 {{ match ($contest->status) {
'active'=>'bg-green-100 text-green-800',
'voting'=>'bg-blue-100 text-blue-800',
'ended'=>'bg-gray-100 text-gray-400',
 default =>'bg-yellow-100 text-yellow-700',
 } }}">
 {{ ucfirst($contest->status) }}
 </span>
 </div>

 @if ($contest->description)
 <p class="text-gray-400 mb-4 leading-relaxed">{{ $contest->description }}</p>
 @endif

 <div class="flex flex-wrap items-center gap-4 text-sm text-gray-400 border-t border-gray-100 pt-4">
 <span>📅 {{ $contest->starts_at->format('M j') }} – {{ $contest->ends_at->format('M j, Y') }}</span>
 @if ($contest->prize) <span>🎁 {{ $contest->prize }}</span> @endif
 @if ($contest->species) <span>🐾 {{ ucfirst($contest->species) }} only</span> @endif
 <span>📷 {{ $contest->entries->count() }} entries</span>
 </div>
 </div>

 {{-- Entry form --}}
 @auth
 @if ($contest->isActive() && !$userEntry)
 <div class="bg-emerald-50 rounded-xl border border-emerald-200 p-5 mb-6">
 <h3 class="font-bold text-emerald-800 mb-3">📸 Submit Your Entry</h3>
 <form action="{{ route('contests.entries.store', $contest->slug) }}" method="POST" enctype="multipart/form-data"
 class="space-y-3">
 @csrf
 <input type="file" name="photo" accept="image/*"required class="block w-full text-sm">
 <textarea name="caption" rows="2" placeholder="Caption (optional)"
 class="w-full rounded-lg border-gray-300 text-sm"></textarea>
 <button type="submit"
 class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
 Submit Entry
 </button>
 </form>
 </div>
 @endif
 @endauth

 {{-- Entries grid --}}
 <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
 @foreach ($contest->entries as $entry)
 <div
 class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden {{ $entry->is_winner ?'ring-2 ring-yellow-400':''}}">
 @if ($entry->getFirstMediaUrl('entry-photo'))
 <img src="{{ $entry->getFirstMediaUrl('entry-photo') }}" class="w-full h-48 object-cover"
 alt="Entry by {{ $entry->user->name ??'User'}}">
 @endif
 <div class="p-4">
 <div class="flex items-center gap-2 mb-2">
 <span class="font-semibold text-sm text-gray-400">{{ $entry->user->name ??'User'}}</span>
 @if ($entry->is_winner) <span
 class="text-xs bg-yellow-100 text-yellow-800 px-2 py-0.5 rounded-full font-medium">🥇
 Winner</span> @endif
 </div>
 @if ($entry->caption)
 <p class="text-sm text-gray-400 mb-3">{{ $entry->caption }}</p>
 @endif
 <div class="flex items-center justify-between">
 <span class="text-sm text-gray-400">{{ $entry->votes_count ?? 0 }} votes</span>
 @auth
 @if ($contest->status ==='voting'&& !$hasVoted && (int) $entry->user_id !== auth()->id())
 <form action="{{ route('contests.entries.vote', [$contest->slug, $entry]) }}" method="POST">
 @csrf
 <button
 class="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700">Vote</button>
 </form>
 @endif
 @endauth
 </div>
 </div>
 </div>
 @endforeach
 </div>
 </div>
@endsection