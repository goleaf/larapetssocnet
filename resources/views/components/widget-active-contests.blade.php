@props(['contest'])

<section class="rounded-2xl border border-gray-200 bg-white p-4">
 <h3 class="text-sm font-semibold text-gray-900">Active Contest</h3>
 <a href="{{ route('explore.index') }}"class="mt-3 block rounded-lg border border-gray-100 p-3 hover:bg-gray-50">
 <p class="text-sm font-medium text-gray-900">{{ $contest->title }}</p>
 <p class="text-xs text-gray-500">Deadline: {{ optional($contest->ends_at)->format('M j, Y') }}</p>
 <p class="text-xs text-gray-500">Entries: {{ $contest->entries_count }}</p>
 </a>
</section>
