@extends('layouts.app')
@section('title','Admin – Posts')

@section('content')
 <div class="max-w-6xl mx-auto">
 <div class="flex items-center justify-between mb-6">
 <h1 class="text-2xl font-bold text-gray-900">📝 Manage Posts</h1>
 <a href="{{ route('admin.dashboard') }}" class="text-sm text-gray-500 hover:text-gray-700">← Dashboard</a>
 </div>

 <form method="GET" class="flex gap-3 mb-6">
 <input type="text" name="q" value="{{ request('q') }}" placeholder="Search posts…"
 class="flex-1 rounded-lg border-gray-300 text-sm">
 <select name="filter" class="rounded-lg border-gray-300 text-sm">
 <option value="">All</option>
 <option value="deleted"@selected(request('filter') ==='deleted')>Deleted</option>
 <option value="reported"@selected(request('filter') ==='reported')>Reported</option>
 </select>
 <button type="submit" class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium">Search</button>
 </form>

 @forelse ($posts as $post)
 <div
 class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-3 {{ $post->deleted_at ?'opacity-50':''}}">
 <div class="flex justify-between items-start">
 <div>
 <p class="text-sm text-gray-900">{{ Str::limit($post->body, 120) }}</p>
 <p class="text-xs text-gray-400 mt-1">
 by {{ $post->author->name ??'Deleted'}} · {{ $post->created_at->diffForHumans() }}
 @if ($post->deleted_at) <span class="text-red-500 font-medium ml-1">Deleted</span> @endif
 </p>
 </div>
 <div class="flex gap-1">
 @if ($post->deleted_at)
 <form x-data
 @submit.prevent="fetch('{{ route('admin.posts.restore', $post->id) }}', { method:'POST', headers: {'X-CSRF-TOKEN':'{{ csrf_token() }}'} }).then(() => location.reload())">
 <button class="rounded bg-green-100 px-2 py-1 text-xs text-green-600">Restore</button>
 </form>
 @else
 <form x-data
 @submit.prevent="fetch('{{ route('admin.posts.destroy', $post) }}', { method:'DELETE', headers: {'X-CSRF-TOKEN':'{{ csrf_token() }}'} }).then(() => location.reload())">
 <button class="rounded bg-red-100 px-2 py-1 text-xs text-red-600">Delete</button>
 </form>
 @endif
 </div>
 </div>
 </div>
 @empty
 <p class="text-center text-gray-400 py-8">No posts found.</p>
 @endforelse

 {{ $posts->appends(request()->query())->links() }}
 </div>
@endsection