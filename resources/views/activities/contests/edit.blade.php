@extends('layouts.app')
@section('title', 'Edit Contest')

@section('content')
 <div class="mx-auto max-w-2xl">
 <div class="mb-6 flex items-center justify-between">
 <h1 class="text-2xl font-bold text-gray-900">Edit Contest</h1>
 <a href="{{ route('contests.show', $contest->slug) }}" class="text-sm text-gray-500 hover:text-gray-700">Back to contest</a>
 </div>

 <form action="{{ route('contests.update', $contest->slug) }}" method="POST"
 class="space-y-5 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
 @csrf
 @method('PATCH')

 <div>
 <label for="title" class="mb-1 block text-sm font-medium text-gray-700">Title</label>
 <input id="title" type="text" name="title" value="{{ old('title', $contest->title) }}" required maxlength="150"
 class="w-full rounded-lg border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
 @error('title') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
 </div>

 <div>
 <label for="description" class="mb-1 block text-sm font-medium text-gray-700">Description</label>
 <textarea id="description" name="description" rows="4" maxlength="2000"
 class="w-full rounded-lg border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">{{ old('description', $contest->description) }}</textarea>
 @error('description') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
 </div>

 <div class="grid gap-4 md:grid-cols-2">
 <div>
 <label for="starts_at" class="mb-1 block text-sm font-medium text-gray-700">Start Date</label>
 <input id="starts_at" type="datetime-local" name="starts_at"
 value="{{ old('starts_at', optional($contest->starts_at)->format('Y-m-d\\TH:i')) }}" required
 class="w-full rounded-lg border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
 @error('starts_at') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
 </div>

 <div>
 <label for="ends_at" class="mb-1 block text-sm font-medium text-gray-700">End Date</label>
 <input id="ends_at" type="datetime-local" name="ends_at"
 value="{{ old('ends_at', optional($contest->ends_at)->format('Y-m-d\\TH:i')) }}" required
 class="w-full rounded-lg border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
 @error('ends_at') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
 </div>
 </div>

 <div>
 <label for="prize" class="mb-1 block text-sm font-medium text-gray-700">Prize</label>
 <input id="prize" type="text" name="prize" value="{{ old('prize', $contest->prize) }}" maxlength="255"
 class="w-full rounded-lg border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
 @error('prize') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
 </div>

 <div class="flex justify-end gap-3">
 <a href="{{ route('contests.show', $contest->slug) }}" class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-200">Cancel</a>
 <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-emerald-700">
 Save Changes
 </button>
 </div>
 </form>
 </div>
@endsection
