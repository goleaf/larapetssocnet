@php
 $sections = [
 ['title' => 'Today', 'items' => $todayNotifications],
 ['title' => 'This Week', 'items' => $thisWeekNotifications],
 ['title' => 'Older', 'items' => $olderNotifications],
 ];
@endphp

@section('title','Notifications')

<x-app-layout>
 <x-slot name="header">
 <div class="flex flex-wrap items-start justify-between gap-3">
 <x-ui.page-header title="Notifications" description="Stay on top of activity around your pets and community." icon="🔔" />

 @if ($unreadCount > 0)
 <form method="POST" action="{{ route('notifications.read-all') }}">
 @csrf
 @method('PATCH')
 <x-ui.button type="submit" variant="ghost" size="sm">Mark all as read</x-ui.button>
 </form>
 @endif
 </div>
 </x-slot>

 <section class="shell-card p-5 sm:p-6">
 @if ($notifications->isEmpty())
 <x-ui.empty-state
 icon="🔔"
 title="No notifications yet"
 description="New followers, comments, and other updates will appear here."
 />
 @else
 <div class="space-y-7">
 @foreach ($sections as $section)
 @php
 $sectionItems = $section['items'];
 @endphp

 @continue($sectionItems->isEmpty())

 <section class="{{ $loop->first ? '' : 'border-t pt-6' }}" style="{{ $loop->first ? '' : 'border-color: var(--ui-border);' }}">
 <div class="mb-3 flex items-center justify-between">
 <h2 class="shell-title text-sm uppercase tracking-[0.08em]">{{ $section['title'] }}</h2>
 <x-ui.badge size="sm">{{ $sectionItems->count() }}</x-ui.badge>
 </div>

 <div class="space-y-3">
 @foreach ($sectionItems as $notification)
 @php
 $data = is_array($notification->data) ? $notification->data : [];
 $message = $data['message'] ?? 'You have a new notification.';
 $route = $data['route'] ?? route('notifications.index');
 $isUnread = $notification->read_at === null;
 $cardBackground = $isUnread
 ? 'color-mix(in srgb, var(--ui-primary) 7%, var(--ui-surface) 93%)'
 : 'color-mix(in srgb, var(--ui-surface) 95%, white 5%)';
 @endphp

 <article class="border px-4 py-3" style="border-color: var(--ui-border); background: {{ $cardBackground }};">
 <div class="flex items-start gap-3">
 <span class="mt-1 h-2.5 w-2.5 shrink-0 rounded-[var(--radius-soft)] {{ $isUnread ? 'bg-emerald-500' : 'bg-slate-300' }}"></span>

 <div class="min-w-0 flex-1">
 <a href="{{ $route }}" class="block text-sm font-semibold leading-5 text-[var(--ui-text)] hover:underline">
 {{ $message }}
 </a>
 <p class="mt-1 text-xs shell-text-muted">{{ $notification->created_at?->diffForHumans() }}</p>
 </div>

 @if ($isUnread)
 <form method="POST" action="{{ route('notifications.read', ['notification' => $notification->id]) }}">
 @csrf
 @method('PATCH')

 <x-ui.button type="submit" variant="ghost" size="xs">Mark read</x-ui.button>
 </form>
 @endif
 </div>
 </article>
 @endforeach
 </div>
 </section>
 @endforeach
 </div>

 <div class="mt-6">
 {{ $notifications->links() }}
 </div>
 @endif
 </section>
</x-app-layout>
