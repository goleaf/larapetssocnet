<x-app-layout>
 <x-slot name="header">
 <div class="flex items-center justify-between gap-4">
 <h2 class="font-semibold text-xl text-gray-400 leading-tight">
 Pet Care Tips
 </h2>
 <a href="{{ route('tips.create') }}" class="text-sm text-indigo-600 hover:text-indigo-800">Share a tip</a>
 </div>
 </x-slot>

 <div class="py-8">
 <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
 @if (session('status'))
 <div class="rounded-md bg-green-50 p-4 text-sm text-green-700">
 {{ session('status') }}
 </div>
 @endif

 <div class="bg-white shadow-sm sm:rounded-lg p-6">
 <form method="GET" action="{{ route('tips.index') }}" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
 <div class="lg:col-span-2">
 <x-input-label for="q" value="Search" />
 <x-text-input id="q" name="q" class="mt-1 block w-full" :value="$filters['q']" placeholder="Title or content" />
 </div>

 <div>
 <x-input-label for="species" value="Species" />
 <x-text-input id="species" name="species" class="mt-1 block w-full" :value="$filters['species']" />
 </div>

 <div>
 <x-input-label for="sort" value="Sort" />
 <select id="sort" name="sort" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
 <option value="latest"@selected($filters['sort'] ==='latest')>Latest</option>
 <option value="oldest"@selected($filters['sort'] ==='oldest')>Oldest</option>
 <option value="helpful"@selected($filters['sort'] ==='helpful')>Most helpful</option>
 </select>
 </div>

 <div class="sm:col-span-2 lg:col-span-4 flex items-center justify-end gap-2">
 <x-primary-button>Apply filters</x-primary-button>
 <a href="{{ route('tips.index') }}" class="text-sm text-gray-400 hover:text-gray-400">Reset</a>
 </div>
 </form>
 </div>

 @if($tips->isEmpty())
 <div class="rounded-lg border border-dashed border-gray-300 p-8 text-center text-sm text-gray-400">
 No tips found.
 </div>
 @else
 <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
 @foreach($tips as $tip)
 @php
 $tipSlug = $tip->slug ?? $tip->getKey();
 $isApproved = (bool) data_get($tip,'is_approved', true);
 @endphp
 <article class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
 <div class="flex items-center gap-2">
 @if($isApproved)
 <span class="inline-flex rounded-full bg-emerald-100 px-3 py-1 text-xs font-medium text-emerald-700">Approved</span>
 @else
 <span class="inline-flex rounded-full bg-amber-100 px-3 py-1 text-xs font-medium text-amber-700">Pending approval</span>
 @endif

 @if(!empty($tip->species))
 <span class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600">{{ $tip->species }}</span>
 @endif
 </div>

 <h3 class="mt-3 text-lg font-semibold text-gray-400">{{ $tip->title }}</h3>
 <p class="mt-2 text-sm text-gray-400">{{ \Illuminate\Support\Str::limit((string) $tip->content, 140) }}</p>

 <div class="mt-4 flex items-center justify-between text-sm text-gray-400">
 <span>{{ data_get($tip,'helpful_count', 0) }} helpful</span>
 <a href="{{ route('tips.show', $tipSlug) }}" class="text-indigo-600 hover:text-indigo-800">Read tip</a>
 </div>
 </article>
 @endforeach
 </div>

 <div>
 {{ $tips->links() }}
 </div>
 @endif
 </div>
 </div>
</x-app-layout>
