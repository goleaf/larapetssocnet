@extends('layouts.app')
@section('title','Create Contest')

@section('content')
 <div class="max-w-2xl mx-auto">
 <h1 class="text-2xl font-bold text-gray-900 mb-6">🏆 Create a Contest</h1>

 <form action="{{ route('contests.store') }}" method="POST" enctype="multipart/form-data"
 class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-5">
 @csrf
 <div>
 <label class="block text-sm font-medium text-gray-700 mb-1">Title</label>
 <input type="text" name="title" value="{{ old('title') }}" required maxlength="150"
 class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-emerald-500 focus:border-emerald-500">
 @error('title') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
 </div>
 <div>
 <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
 <textarea name="description" rows="3" maxlength="2000"
 class="w-full rounded-lg border-gray-300 shadow-sm">{{ old('description') }}</textarea>
 </div>
 <div class="grid grid-cols-2 gap-4">
 <div>
 <label class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
 <input type="datetime-local" name="starts_at" value="{{ old('starts_at') }}" required
 class="w-full rounded-lg border-gray-300">
 </div>
 <div>
 <label class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
 <input type="datetime-local" name="ends_at" value="{{ old('ends_at') }}" required
 class="w-full rounded-lg border-gray-300">
 </div>
 </div>
 <div class="grid grid-cols-2 gap-4">
 <div>
 <label class="block text-sm font-medium text-gray-700 mb-1">Prize</label>
 <input type="text" name="prize" value="{{ old('prize') }}" maxlength="255"
 class="w-full rounded-lg border-gray-300">
 </div>
 <div>
 <label class="block text-sm font-medium text-gray-700 mb-1">Species</label>
 <select name="species" class="w-full rounded-lg border-gray-300">
 <option value="">Any</option>
 <option value="dog" @selected(old('species') ==='dog')>Dogs</option>
 <option value="cat" @selected(old('species') ==='cat')>Cats</option>
 <option value="bird" @selected(old('species') ==='bird')>Birds</option>
 <option value="other" @selected(old('species') ==='other')>Other</option>
 </select>
 </div>
 </div>
 <div>
 <label class="block text-sm font-medium text-gray-700 mb-1">Cover Image</label>
 <input type="file" name="cover" accept="image/*" class="block w-full text-sm">
 </div>
 <button type="submit"
 class="w-full rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow hover:bg-emerald-700">
 Create Contest
 </button>
 </form>
 </div>
@endsection