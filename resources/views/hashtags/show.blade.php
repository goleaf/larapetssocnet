<!DOCTYPE html>
<html lang="{{ str_replace('_','-', app()->getLocale()) }}">
<head>
 <meta charset="utf-8">
 <meta name="viewport"content="width=device-width, initial-scale=1">
 <title>#{{ $hashtag->name }} - {{ config('app.name','LaraPets') }}</title>
 @vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body class="bg-gray-100 text-gray-900">
 <header class="border-b border-gray-200 bg-white">
 <div class="mx-auto flex max-w-5xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
 <a href="{{ route('explore.index') }}" class="text-lg font-semibold text-gray-900">{{ config('app.name','LaraPets') }}</a>
 <a href="{{ route('explore.index', ['q'=>'#'.$hashtag->name]) }}" class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Back to Explore</a>
 </div>
 </header>

 <main class="py-8">
 <div class="mx-auto max-w-5xl space-y-4 px-4 sm:px-6 lg:px-8">
 <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
 <h1 class="text-xl font-semibold text-gray-900">#{{ $hashtag->name }}</h1>
 <p class="mt-1 text-sm text-gray-600">Posts tagged with this hashtag.</p>
 </section>

 @forelse ($posts as $post)
 @include('posts.partials.card', ['post'=> $post])
 @empty
 <div class="rounded-xl border border-dashed border-gray-300 bg-white p-8 text-center text-gray-600">
 No posts found for this hashtag.
 </div>
 @endforelse

 <div>
 {{ $posts->links() }}
 </div>
 </div>
 </main>
</body>
</html>
