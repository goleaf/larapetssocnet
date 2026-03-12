<x-app-layout>
 <x-slot name="header">
 <x-ui.page-header title="Pet Care Tips" description="Discover, filter, and share practical pet advice." icon="📚">
 <x-slot name="action">
 <x-ui.button :href="route('tips.create')" variant="ghost" size="sm">Share a tip</x-ui.button>
 </x-slot>
 </x-ui.page-header>
 </x-slot>

 <div class="py-8">
 <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
 @if (session('status'))
 <x-ui.alert type="success">{{ session('status') }}</x-ui.alert>
 @endif

 <x-ui.card padding="lg">
 <form method="GET" action="{{ route('tips.index') }}" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
 <div class="lg:col-span-2">
 <x-ui.input id="q" name="q" label="Search" :value="$filters['q']" placeholder="Title or content"/>
 </div>

 <div>
 <x-ui.input id="species" name="species" label="Species" :value="$filters['species']"/>
 </div>

 <div>
 <x-ui.select
 id="sort"
 name="sort"
 label="Sort"
 :options="[
 'latest' => 'Latest',
 'oldest' => 'Oldest',
 'helpful' => 'Most helpful',
 ]"
 :selected="$filters['sort']"
 />
 </div>

 <div class="sm:col-span-2 lg:col-span-4 flex items-center justify-end gap-2">
 <x-ui.button variant="primary">Apply filters</x-ui.button>
 <x-ui.button :href="route('tips.index')" variant="ghost">Reset</x-ui.button>
 </div>
 </form>
 </x-ui.card>

 @if($tips->isEmpty())
 <x-ui.card padding="lg" class="border-dashed">
 <div class="text-center text-sm text-gray-500">
 No tips found.
 </div>
 </x-ui.card>
	 @else
	 <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
	 @foreach($tips as $tip)
 <x-ui.card padding="md">
	 <div class="flex items-center gap-2">
	 @if((bool) data_get($tip, 'is_approved', true))
 <x-ui.badge variant="success" size="sm">Approved</x-ui.badge>
	 @else
 <x-ui.badge variant="warning" size="sm">Pending approval</x-ui.badge>
	 @endif

 @if(!empty($tip->species))
 <x-ui.badge variant="default" size="sm">{{ $tip->species }}</x-ui.badge>
 @endif
 </div>

 <h3 class="mt-3 text-lg font-semibold text-gray-900">{{ $tip->title }}</h3>
 <p class="mt-2 text-sm text-gray-600">{{ \Illuminate\Support\Str::limit((string) $tip->content, 140) }}</p>

	 <div class="mt-4 flex items-center justify-between text-sm text-gray-500">
	 <span>{{ data_get($tip,'helpful_count', 0) }} helpful</span>
 <x-ui.button :href="route('tips.show', $tip->slug ?? $tip->getKey())" variant="ghost" size="sm">Read tip</x-ui.button>
	 </div>
 </x-ui.card>
	 @endforeach
 </div>

 <div>
 {{ $tips->links() }}
 </div>
 @endif
 </div>
 </div>
</x-app-layout>
