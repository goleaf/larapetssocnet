@extends('layouts.app')
@section('title', 'Admin – ' . $user->name)

@section('content')
    <div class="max-w-4xl mx-auto">
        <a href="{{ route('admin.users.index') }}" class="text-sm text-gray-500 hover:text-gray-700">← All Users</a>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mt-4 mb-6">
            <div class="flex items-start justify-between">
                <div>
                    <h1 class="text-xl font-bold text-gray-900">{{ $user->name }}</h1>
                    <p class="text-sm text-gray-500">@ {{ $user->username }} · {{ $user->email }}</p>
                    <div class="flex gap-3 mt-2 text-xs text-gray-400">
                        <span>{{ $user->posts_count ?? 0 }} posts</span>
                        <span>{{ $user->pets_count ?? 0 }} pets</span>
                        <span>{{ $user->followers_count ?? 0 }} followers</span>
                        <span>Joined {{ $user->created_at->format('M j, Y') }}</span>
                    </div>
                </div>
                <div class="flex gap-2">
                    @if ($user->is_banned)
                        <form x-data
                            @submit.prevent="fetch('{{ route('admin.users.unban', $user) }}', { method: 'POST', headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}'} }).then(() => location.reload())">
                            <button
                                class="rounded-lg bg-green-100 px-3 py-1.5 text-xs font-medium text-green-700 hover:bg-green-200">Unban</button>
                        </form>
                    @else
                        <form x-data
                            @submit.prevent="fetch('{{ route('admin.users.ban', $user) }}', { method: 'POST', headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}'} }).then(() => location.reload())">
                            <button
                                class="rounded-lg bg-red-100 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-200">Ban</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>

        @if ($recentReports->isNotEmpty())
            <h3 class="font-bold text-gray-900 mb-3">Recent Reports</h3>
            @foreach ($recentReports as $report)
                <div class="bg-white rounded-lg border border-gray-100 p-4 mb-2 text-sm">
                    <p class="text-gray-700">{{ $report->reason ?? 'No reason given' }}</p>
                    <p class="text-xs text-gray-400 mt-1">{{ $report->created_at->diffForHumans() }} · Status: {{ $report->status }}
                    </p>
                </div>
            @endforeach
        @endif
    </div>
@endsection