<x-app-layout>
 @php
 $scopeOptions = [
 ['value'=>'upcoming','label'=>__('en.upcoming')],
 ['value'=>'all','label'=>__('en.all')],
 ['value'=>'past','label'=>__('en.past')],
 ['value'=>'cancelled','label'=>__('en.cancelled')],
 ];

 if (auth()->check()) {
 $scopeOptions[] = ['value'=>'mine','label'=>__('en.created_by_me')];
 }

 $groupOptionsForSelect = collect($groupOptions)
 ->map(static fn($groupOption): array => [
'value'=> (string) $groupOption->id,
'label'=> $groupOption->name,
 ])
 ->prepend([
'value'=>'0',
'label'=>__('en.all_groups'),
 ])
 ->values()
 ->all();
 @endphp

 <x-slot name="header">
 <x-ui.page-header :title="__('en.events')" :subtitle="__('en.find_and_join_upcoming_pet_community_events')">
 <x-slot name="action">
 @auth
 <x-ui.button :href="route('events.create')" variant="primary" size="sm">{{ __('en.create_event') }}</x-ui.button>
 @endauth
 </x-slot>
 </x-ui.page-header>
 </x-slot>

 <div class="space-y-5">
 @if (session('status'))
 <x-flash-message type="success" :message="session('status')"  />
 @endif

 <x-ui.card>
 <form method="GET" action="{{ route('events.index') }}" class="grid gap-3 md:grid-cols-12">
 <x-ui.input class="md:col-span-5" name="q" :label="__('en.search')" :value="$search"
 :placeholder="__('en.search_events')"  />

 <x-ui.select class="md:col-span-3" name="scope" :label="__('en.scope')" :options="$scopeOptions"
 :selected="$scope"  />

 <x-ui.select class="md:col-span-4" name="group_id" :label="__('en.group')" :options="$groupOptionsForSelect"
 :selected="(string) $groupId"  />

 <div class="md:col-span-8"></div>

 <div class="flex items-end md:col-span-2">
 <x-ui.button type="submit" variant="primary" size="sm" class="w-full">{{ __('en.apply_filters') }}</x-ui.button>
 </div>

 <div class="flex items-end md:col-span-2">
 <x-ui.button :href="route('events.index')" variant="ghost" size="sm"
 class="w-full">{{ __('en.reset') }}</x-ui.button>
 </div>
 </form>
 </x-ui.card>

 @if ($events->isEmpty())
 <x-ui.card>
 <x-ui.empty-state icon="📅" :title="__('en.no_events_found')"
 :description="__('en.try_changing_your_filters_or_create_a_new_event')"  />
 </x-ui.card>
 @else
 <p class="text-sm text-fur">{{ __('en.param_events_found', ['count' => number_format($events->total())]) }}</p>

 <div class="mt-4 flex flex-col gap-4 max-w-5xl mx-auto">
 @foreach ($events as $event)
 @php
 $startAt = data_get($event, $startColumn) ?? $event->start_at ?? $event->starts_at;
 $location = data_get($event,'event_location')
 ?? data_get($event,'location_text')
 ?? data_get($event,'location');
 $status = \Illuminate\Support\Str::headline((string) ($event->status ??'scheduled'));
 $groupRouteKey = filled((string) ($event->group_slug ??'')) ? $event->group_slug : ($event->group_id ?? null);
 @endphp

 <x-ui.card>
 <div class="flex items-start justify-between gap-3">
 <h3 class="text-base font-semibold font-display text-bark">
 <a href="{{ route('events.show', $event->id) }}" class="hover:underline">{{ $event->title }}</a>
 </h3>
 <x-ui.badge variant="outline" size="sm">{{ $status }}</x-ui.badge>
 </div>

 <div class="mt-3 space-y-1 text-xs text-fur">
 <p>{{ $startAt ? \Carbon\Carbon::parse($startAt)->format('M j, Y g:i A') : __('en.tba') }}</p>

 @if ($location)
 <p>{{ $location }}</p>
 @endif

 @if (!empty($event->group_name) && $groupRouteKey)
 <p>
 {{ __('en.group_2') }}
 <a class="underline"
 href="{{ route('groups.show', $groupRouteKey) }}">{{ $event->group_name }}</a>
 </p>
 @endif

 @if (!empty($event->creator_name))
 <p>{{ __('en.host_param', ['name' => $event->creator_name]) }}</p>
 @endif

 <p>{{ __('en.param_going', ['count' => number_format((int) ($event->attendees_count ?? 0))]) }}</p>
 </div>

 <div class="mt-4">
 <x-ui.button :href="route('events.show', $event->id)" variant="primary" size="sm"
 class="w-full">{{ __('en.view_event') }}</x-ui.button>
 </div>
 </x-ui.card>
 @endforeach
 </div>

 <x-ui.card>
 <x-ui.pagination :paginator="$events"  />
 </x-ui.card>
 @endif
 </div>
</x-app-layout>
