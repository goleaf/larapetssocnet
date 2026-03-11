@props(['hashtags'])

<section class="rounded-2xl border border-gray-200 bg-white p-4">
 <h3 class="text-sm font-semibold text-gray-900">Trending Hashtags</h3>
 <ul class="mt-3 space-y-2">
 @foreach ($hashtags as $hashtag)
 <li>
 <a href="{{ route('hashtags.show', $hashtag) }}"class="flex items-center justify-between text-sm text-gray-700 hover:text-emerald-600">
 <span>#{{ $hashtag->name }}</span>
 <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-500">{{ $hashtag->posts_count }}</span>
 </a>
 </li>
 @endforeach
 </ul>
</section>
