@extends('layouts.app')
@section('title', $user->name . "'s Badges")

@section('content')
<div class="max-w-3xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">{{ $user->name }}'s Badges</h1>

    @if ($badges->isEmpty())
        <div class="bg-white rounded-xl shadow-sm p-8 text-center text-gray-400">
            <div class="text-4xl mb-3">🏅</div>
            <p>No badges earned yet.</p>
        </div>
    @else
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
            @foreach ($badges as $badge)
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 text-center hover:shadow-md transition">
                    <div class="text-4xl mb-2">{{ $badge->icon }}</div>
                    <p class="font-semibold text-gray-900 text-sm">{{ $badge->name }}</p>
                    @if ($badge->description)
                        <p class="text-xs text-gray-500 mt-1">{{ $badge->description }}</p>
                    @endif
                    <p class="text-xs text-gray-400 mt-2">Earned {{ $badge->pivot->awarded_at ? \Carbon\Carbon::parse($badge->pivot->awarded_at)->diffForHumans() : '' }}</p>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
