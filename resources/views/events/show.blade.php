<x-app-layout>
 @php
 $location = data_get($event, $locationColumn)
 ?? data_get($event,'location_text')
 ?? data_get($event,'location');
 $statusValue = strtolower((string) (data_get($event, $statusColumn) ??'scheduled'));
 @endphp

 <x-slot name="header">
 <x-ui.page-header
 :title="$event->title"
 :description="$startAt ? ($startAt->format('M j, Y g:i A').($endAt ? ' - '.$endAt->format('M j, Y g:i A') : '')) : 'TBA'"
 icon="📅"
 >
 <x-slot name="action">
 <div class="flex flex-wrap items-center gap-2">
 <a href="{{ route('events.index') }}" class="btn-base btn-ghost px-3 py-2 text-sm">Back</a>
 <a href="{{ route('events.ics', $event->id) }}" class="btn-base btn-ghost px-3 py-2 text-sm">Download ICS</a>

 @auth
 @if ($canManage)
 <a href="{{ route('events.edit', $event->id) }}" class="btn-base btn-ghost px-3 py-2 text-sm">Edit</a>
 @if ($statusValue !=='cancelled')
 <form method="POST" action="{{ route('events.cancel', $event->id) }}">
 @csrf
 @method('PATCH')
 <button type="submit" class="btn-base btn-danger px-3 py-2 text-sm">Cancel Event</button>
 </form>
 @endif
 @endif
 @endauth
 </div>
 </x-slot>
 </x-ui.page-header>
 </x-slot>

 <div class="py-8">
 <div class="mx-auto max-w-5xl space-y-5 px-4 sm:px-6 lg:px-8">
 @if (session('status'))
 <x-flash-message type="success" :message="session('status')"/>
 @endif

 @if ($errors->any())
 <x-flash-message type="error" :message="$errors->first()"/>
 @endif

 <section class="shell-card space-y-4 p-5">
 <div class="flex flex-wrap items-center gap-3">
 <span class="chip">{{ \Illuminate\Support\Str::headline($statusValue) }}</span>
 <span class="text-sm shell-text-muted">{{ $attendeesCount }} going</span>
 @if ($maxAttendees)
 <span class="text-sm shell-text-muted">{{ $maxAttendees }} max attendees</span>
 @endif
 @if ($isFull)
 <span class="chip">Full</span>
 @endif
 </div>

 @if (! empty($event->description))
 <p class="text-sm">{{ $event->description }}</p>
 @endif

 <div class="space-y-1 text-sm shell-text-muted">
 @if ($location)
 <p>Location: {{ $location }}</p>
 @endif
 @if ($creator)
 <p>Host: {{ $creator->name }}</p>
 @endif
 @if ($group)
 <p>
 Group:
 <a href="{{ route('groups.show', $groupRouteKey) }}" class="underline">
 {{ $group->name }}
 </a>
 </p>
 @endif
 </div>
 </section>

 @auth
 <section class="shell-card p-5">
 <h3 class="shell-title text-base">RSVP</h3>
 <p class="mt-1 text-sm shell-text-muted">Choose your attendance status. Clicking your current status removes your RSVP.</p>
 <form method="POST" action="{{ route('events.rsvp', $event->id) }}" class="mt-4 flex flex-wrap items-center gap-2">
 @csrf
 <button type="submit" name="status" value="going" class="btn-base px-3 py-2 text-sm {{ $viewerRsvp ==='going'?'btn-primary':'btn-ghost'}}" @disabled($isFull && $viewerRsvp !=='going')>
 Going
 </button>
 <button type="submit" name="status" value="maybe" class="btn-base px-3 py-2 text-sm {{ $viewerRsvp ==='maybe'?'btn-primary':'btn-ghost'}}">
 Maybe
 </button>
 <button type="submit" name="status" value="not_going" class="btn-base px-3 py-2 text-sm {{ $viewerRsvp ==='not_going'?'btn-primary':'btn-ghost'}}">
 Not Going
 </button>
 </form>
 </section>
 @endauth

 <section class="shell-card p-5">
 <h3 class="shell-title text-base">Attendees</h3>
 <div class="mt-4 space-y-3">
 @forelse ($attendees as $attendee)
 @php
 $normalized = match ($attendee->status) {
'going'=>'going',
'interested','maybe'=>'maybe',
 default =>'not_going',
 };
 @endphp
 <div class="flex items-center justify-between gap-3 rounded-xl border border-[var(--ui-border)] px-4 py-3">
 <div>
 <p class="font-medium">{{ $attendee->user?->name ??'Unknown user'}}</p>
 @if ($attendee->user?->username)
 <p class="text-xs shell-text-muted">&#64;{{ $attendee->user->username }}</p>
 @endif
 </div>
 <span class="chip">{{ \Illuminate\Support\Str::headline($normalized) }}</span>
 </div>
 @empty
 <p class="text-sm shell-text-muted">No attendees yet.</p>
 @endforelse
 </div>
 </section>
 </div>
 </div>
</x-app-layout>
