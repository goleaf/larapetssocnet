@extends('layouts.app')
@section('title', 'Contests')

@section('content')
    <div class="max-w-5xl mx-auto">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold text-gray-900">🏆 Pet Contests</h1>
            @auth
                <a href="{{ route('contests.create') }}"
                    class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-emerald-700">
                    + New Contest
                </a>
            @endauth
        </div>

        @forelse ($contests as $contest)
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-4 hover:shadow-md transition">
                <div class="flex justify-between items-start">
                    <div>
                        <a href="{{ route('contests.show', $contest->slug) }}"
                            class="text-lg font-bold text-gray-900 hover:text-emerald-600">
                            {{ $contest->title }}
                        </a>
                        <p class="text-sm text-gray-500 mt-1">{{ Str::limit($contest->description, 120) }}</p>
                        <div class="flex items-center gap-3 mt-3 text-xs text-gray-400">
                            <span>📅 {{ $contest->starts_at->format('M j') }} – {{ $contest->ends_at->format('M j, Y') }}</span>
                            <span>📷 {{ $contest->entries_count ?? 0 }} entries</span>
                            @if ($contest->prize)
                                <span>🎁 {{ $contest->prize }}</span>
                            @endif
                        </div>
                    </div>
                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium
                            {{ match ($contest->status) {
                'active' => 'bg-green-100 text-green-800',
                'voting' => 'bg-blue-100 text-blue-800',
                'ended' => 'bg-gray-100 text-gray-600',
                default => 'bg-yellow-100 text-yellow-700',
            } }}">
                        {{ ucfirst($contest->status) }}
                    </span>
                </div>
            </div>
        @empty
            <div class="bg-white rounded-xl shadow-sm p-8 text-center text-gray-400">
                <div class="text-4xl mb-3">🏆</div>
                <p>No contests yet. Be the first to create one!</p>
            </div>
        @endforelse

        {{ $contests->links() }}
    </div>
@endsection