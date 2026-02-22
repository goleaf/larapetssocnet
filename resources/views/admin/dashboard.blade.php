@extends('layouts.app')
@section('title', 'Admin Dashboard')

@section('content')
    <div class="max-w-6xl mx-auto">
        <h1 class="text-2xl font-bold text-gray-900 mb-6">📊 Admin Dashboard</h1>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-8">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <p class="text-3xl font-bold text-emerald-600">{{ number_format($stats['users_total']) }}</p>
                <p class="text-sm text-gray-500 mt-1">Total Users</p>
                <p class="text-xs text-gray-400">+{{ $stats['users_today'] }} today · +{{ $stats['users_week'] }} this week
                </p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <p class="text-3xl font-bold text-blue-600">{{ number_format($stats['posts_total']) }}</p>
                <p class="text-sm text-gray-500 mt-1">Total Posts</p>
                <p class="text-xs text-gray-400">+{{ $stats['posts_today'] }} today</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <p class="text-3xl font-bold text-purple-600">{{ number_format($stats['pets_total']) }}</p>
                <p class="text-sm text-gray-500 mt-1">Pets</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <p class="text-3xl font-bold text-orange-600">{{ $stats['reports_pending'] }}</p>
                <p class="text-sm text-gray-500 mt-1">Pending Reports</p>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <p class="text-xl font-bold text-gray-700">{{ number_format($stats['groups_total']) }}</p>
                <p class="text-sm text-gray-500 mt-1">Groups</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <p class="text-xl font-bold text-gray-700">{{ $stats['contests_active'] }}</p>
                <p class="text-sm text-gray-500 mt-1">Active Contests</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <p class="text-sm font-medium text-gray-700 mb-2">Trending Hashtags</p>
                @forelse ($stats['top_hashtags'] as $tag)
                    <span
                        class="inline-block bg-gray-100 text-xs text-gray-600 rounded-full px-2 py-0.5 mr-1 mb-1">#{{ $tag->name }}</span>
                @empty
                    <span class="text-xs text-gray-400">None</span>
                @endforelse
            </div>
        </div>

        <div class="flex flex-wrap gap-3">
            <a href="{{ route('admin.users.index') }}"
                class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">👤 Manage
                Users</a>
            <a href="{{ route('admin.posts.index') }}"
                class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">📝 Manage
                Posts</a>
            <a href="{{ route('admin.reports.index') }}"
                class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">🚩 Reports</a>
        </div>
    </div>
@endsection