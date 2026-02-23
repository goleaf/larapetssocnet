@section('title','Messages')

<x-app-layout>
 <x-slot name="header">
 <x-ui.page-header title="Messages"subtitle="Simple inbox, newest conversations first.":breadcrumbs="[
 ['label'=>'Messages'],
 ]"icon="💬">
 <x-slot:action>
 <x-ui.button variant="outline"size="sm":href="route('marketplace.index')"icon="🛍️">
 Marketplace
 </x-ui.button>
 </x-slot:action>
 </x-ui.page-header>
 </x-slot>

 <div class="mx-auto w-full max-w-3xl space-y-3">
 <x-ui.card>
 <form method="GET"action="{{ route('messages.index') }}"class="flex items-end gap-2">
 <div class="min-w-0 flex-1">
 <x-ui.input name="q"type="search"placeholder="Search by name or username":value="$search"
 prefix="🔎"/>
 </div>

 <x-ui.button type="submit"variant="primary"size="sm">
 Search
 </x-ui.button>
 </form>
 </x-ui.card>

 @if ($threads->isEmpty())
 <x-ui.empty-state icon="💬"title="No conversations yet"
 description="Start a chat from a profile or marketplace listing."/>
 @else
 <x-ui.card padding="none"class="overflow-hidden">
 <div class="divide-y divide-whisker/25">
 @foreach ($threads as $thread)
 @php
 $peer = $thread['peer'];
 $latest = $thread['latest_message'];
 $isUnread = ((int) ($thread['unread_count'] ?? 0)) > 0;
 $preview = trim((string) ($latest->body ??'Message unavailable'));
 @endphp

 <a href="{{ route('messages.conversation', ['peer'=> $peer]) }}"
 class="block px-4 py-3 transition-colors duration-150 hover:bg-cream/60 {{ $isUnread ?'bg-paw-light/25':'bg-warm-white'}}">
 <div class="flex items-center justify-between gap-3">
 <div class="flex min-w-0 items-center gap-3">
 <x-ui.avatar :src="$peer->avatar_url":name="$peer->name"size="sm":online="$isUnread"/>

 <div class="min-w-0">
 <p class="truncate text-sm font-semibold text-bark">{{ $peer->name }}</p>
 <p class="truncate text-xs text-fur">
 {{ $peer->username ?'@'. $peer->username :'Pet lover'}}</p>
 <p
 class="mt-0.5 truncate text-sm {{ $isUnread ?'font-medium text-bark':'text-fur'}}">
 {{ $preview }}</p>
 </div>
 </div>

 <div class="shrink-0 text-right">
 <p class="text-xs text-fur">{{ $latest?->created_at?->diffForHumans() }}</p>

 @if ($isUnread)
 <x-ui.badge variant="success"size="sm"class="mt-1">
 {{ (int) $thread['unread_count'] }}
 </x-ui.badge>
 @endif
 </div>
 </div>
 </a>
 @endforeach
 </div>
 </x-ui.card>
 @endif
 </div>
</x-app-layout>