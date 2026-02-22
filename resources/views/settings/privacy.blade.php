@extends('layouts.app')
@section('title', 'Privacy Settings')

@section('content')
    <div class="max-w-lg mx-auto">
        <h1 class="text-2xl font-bold text-gray-900 mb-6">🔐 Privacy Settings</h1>

        <form method="POST" action="{{ route('settings.privacy.update') }}"
            class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-5">
            @csrf @method('PATCH')
            <div class="flex items-center justify-between">
                <div>
                    <p class="font-medium text-gray-900">Private Account</p>
                    <p class="text-sm text-gray-500">Only approved followers can see your posts and pets.</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="hidden" name="is_private" value="0">
                    <input type="checkbox" name="is_private" value="1" class="sr-only peer" {{ $user->is_private ? 'checked' : '' }}>
                    <div
                        class="w-11 h-6 bg-gray-200 rounded-full peer-checked:bg-emerald-600 peer-focus:ring-2 peer-focus:ring-emerald-300 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-full">
                    </div>
                </label>
            </div>
            <button type="submit"
                class="w-full rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow hover:bg-emerald-700">
                Save Privacy Settings
            </button>
        </form>

        <div class="bg-white rounded-xl shadow-sm border border-red-200 p-6 mt-8">
            <h3 class="text-lg font-bold text-red-600 mb-2">⚠️ Delete Account</h3>
            <p class="text-sm text-gray-500 mb-4">This action is irreversible. All your posts, pets, and data will be
                permanently removed.</p>
            <form method="POST" action="{{ route('settings.account.destroy') }}"
                onsubmit="return confirm('Are you sure? This cannot be undone.')">
                @csrf @method('DELETE')
                <input type="password" name="password" placeholder="Enter your password to confirm" required
                    class="w-full rounded-lg border-red-300 text-sm mb-3">
                <button type="submit"
                    class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 w-full">
                    Delete My Account
                </button>
            </form>
        </div>
    </div>
@endsection