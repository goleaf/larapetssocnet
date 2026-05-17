<x-app-layout>
 @php
 $scopeOptions = [
 ['value'=>'upcoming','label'=>'Upcoming'],
 ['value'=>'all','label'=>'All'],
 ['value'=>'past','label'=>'Past'],
 ['value'=>'cancelled','label'=>'Cancelled'],
 ];

 if (auth()->check()) {
 $scopeOptions[] = ['value'=>'mine','label'=>'Created by me'];
 }

 $groupOptionsForSelect = collect($groupOptions)
 ->map(static fn($groupOption): array => [
'value'=> (string) $groupOption->id,
'label'=> $groupOption->name,
 ])
 ->prepend([
'value'=>'0',
'label'=>'All groups',
 ])
 ->values()
 ->all();
 @endphp

 <x-slot name="header">
 <x-ui.page-header title="Events" subtitle="Find and join upcoming pet community events.">
 <x-slot name="action">
 @auth
 <x-ui.button :href="route('events.create')" variant="primary" size="sm">Create Event</x-ui.button>
 @endauth
 </x-slot>
 </x-ui.page-header>
 </x-slot>

 <div class="space-y-5">
@if (session('status'))
<x-ui.alert type="success">{{ session('status') }}</x-ui.alert>
@endif

 <x-ui.card>
 <form method="GET" action="{{ route('events.index') }}" class="grid gap-3 md:grid-cols-12">
 <x-ui.input class="md:col-span-5" name="q" label="Search" :value="$search"
 placeholder="Search events"/>

 <x-ui.select class="md:col-span-3" name="scope" label="Scope" :options="$scopeOptions"
 :selected="$scope"/>

 <x-ui.select class="md:col-span-4" name="group_id" label="Group" :options="$groupOptionsForSelect"
 :selected="(string) $groupId"/>

 <div class="md:col-span-8"></div>

 <div class="flex items-end md:col-span-2">
 <x-ui.button type="submit" variant="primary" size="sm" class="w-full">Apply Filters</x-ui.button>
 </div>

 <div class="flex items-end md:col-span-2">
 <x-ui.button :href="route('events.index')" variant="ghost" size="sm"
 class="w-full">Reset</x-ui.button>
 </div>
 </form>
 </x-ui.card>

 @if ($events->isEmpty())
 <x-ui.card>
 <x-ui.empty-state icon="📅" title="No Events Found"
 description="Try changing your filters or create a new event."/>
 </x-ui.card>
 @else
 <p class="text-sm text-fur">{{ number_format($events->total()) }} events found</p>

 <div class="mt-4 grid gap-4 lg:grid-cols-2">
 @foreach ($events as $event)
 @php
 $startAt = data_get($event, $startColumn) ?? $event->start_at ?? $event->starts_at;
 $startDate = $startAt ? \Carbon\Carbon::parse($startAt) : null;
 $location = data_get($event,'event_location')
 ?? data_get($event,'location_text')
 ?? data_get($event,'location');
 $status = \Illuminate\Support\Str::headline((string) ($event->status ??'scheduled'));
 $statusTone = match ((string) ($event->status ?? 'scheduled')) {
 'cancelled' => 'danger',
 'completed' => 'neutral',
 default => 'success',
 };
 $groupRouteKey = filled((string) ($event->group_slug ??'')) ? $event->group_slug : ($event->group_id ?? null);
 @endphp

 <article class="shell-card group flex min-h-full flex-col overflow-hidden p-0 transition-all duration-200 hover:-translate-y-0.5 hover:shadow-card-hover focus-within:shadow-card-hover" data-ui="event-card" aria-label="{{ __('Event: :title', ['title' => $event->title]) }}">
 <div class="flex items-start justify-between gap-4 border-b border-whisker/40 bg-cream/50 p-4">
 <div class="flex min-w-0 gap-3">
 <div class="flex h-14 w-14 shrink-0 flex-col items-center justify-center rounded-[var(--radius-card)] border border-whisker/40 bg-warm-white text-center shadow-card">
 <span class="text-2xs font-semibold uppercase text-fur">{{ $startDate?->format('M') ?? 'TBA' }}</span>
 <span class="text-lg font-bold font-display text-bark">{{ $startDate?->format('j') ?? '•' }}</span>
 </div>

 <div class="min-w-0">
 <h3 class="truncate text-base font-semibold font-display text-bark">
 <a href="{{ route('events.show', $event->id) }}" class="hover:text-paw focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">{{ $event->title }}</a>
 </h3>
 @if ($startDate)
 <time datetime="{{ $startDate->toIso8601String() }}" class="mt-1 block text-xs shell-text-muted">{{ $startDate->format('M j, Y g:i A') }}</time>
 @else
 <p class="mt-1 text-xs shell-text-muted">Time to be announced</p>
 @endif
 </div>
 </div>

 <x-ui.badge :tone="$statusTone" size="sm">{{ $status }}</x-ui.badge>
 </div>

 <div class="flex flex-1 flex-col gap-4 p-4">
 <div class="flex flex-wrap gap-2 text-xs shell-text-muted">
 @if ($location)
 <span class="inline-flex min-h-8 items-center rounded-[var(--radius-soft)] border border-whisker/40 px-2.5">📍 {{ $location }}</span>
 @endif

 @if (!empty($event->creator_name))
 <span class="inline-flex min-h-8 items-center rounded-[var(--radius-soft)] border border-whisker/40 px-2.5">Host: {{ $event->creator_name }}</span>
 @endif

 <span class="inline-flex min-h-8 items-center rounded-[var(--radius-soft)] border border-whisker/40 px-2.5">{{ number_format((int) ($event->attendees_count ?? 0)) }} going</span>
 </div>

 @if (!empty($event->description))
 <p class="line-clamp-2 text-sm leading-6 shell-text-muted">{{ \Illuminate\Support\Str::limit(strip_tags((string) $event->description), 140) }}</p>
 @endif

 <div class="mt-auto flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
 @if (!empty($event->group_name) && $groupRouteKey)
 <a class="inline-flex min-h-8 items-center text-sm font-semibold text-paw hover:text-paw-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw"
 href="{{ route('groups.show', $groupRouteKey) }}">{{ $event->group_name }}</a>
 @else
 <span class="text-sm shell-text-muted">Community event</span>
 @endif

 <x-ui.button :href="route('events.show', $event->id)" variant="primary" size="sm" class="min-h-11 sm:shrink-0">View Event</x-ui.button>
 </div>
 </div>
 </article>
 @endforeach
 </div>

 <x-ui.card>
 <x-ui.pagination :paginator="$events"/>
 </x-ui.card>
 @endif
 </div>
</x-app-layout>
