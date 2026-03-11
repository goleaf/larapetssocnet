@section('title','Conversation with'.$peer->name)

<x-app-layout>
 <x-slot name="header">
 <x-ui.page-header
 title="Chat"
 :subtitle="'Simple conversation with'.($peer->username ?'@'.$peer->username : $peer->name)"
 :breadcrumbs="[
 ['label'=>'Messages','href'=> route('messages.index')],
 ['label'=> $peer->name],
 ]"
 icon="💬"
 >
 <x-slot:action>
 <div class="flex flex-wrap items-center gap-2">
 <x-ui.button variant="outline" size="sm" :href="route('messages.index')" icon="↩️">
 Inbox
 </x-ui.button>
 <x-ui.button variant="ghost" size="sm" :href="route('profile.show', ['user'=> $peer])" icon="👤">
 Profile
 </x-ui.button>
 </div>
 </x-slot:action>
 </x-ui.page-header>
 </x-slot>

 <div class="mx-auto w-full max-w-4xl space-y-4">
 @if ($activeListing)
 <x-ui.alert type="info" title="About Listing">
 You are chatting about
 <a href="{{ route('marketplace.show', $activeListing) }}" class="font-semibold underline decoration-dotted underline-offset-2 hover:no-underline">
 {{ $activeListing->title }}
 </a>.
 </x-ui.alert>
 @endif

 @if (! $canSend && $restriction)
 <x-ui.alert type="warning" title="Messaging Restricted" dismissible>
 {{ $restriction }}
 </x-ui.alert>
 @endif

 <x-ui.card padding="none" class="overflow-hidden">
 <header class="border-b border-whisker/30 bg-warm-white px-4 py-3 sm:px-5">
 <div class="flex items-center justify-between gap-3">
 <div class="flex min-w-0 items-center gap-3">
 <x-ui.avatar :src="$peer->avatar_url" :name="$peer->name" size="md"/>
 <div class="min-w-0">
 <p class="truncate text-base font-semibold text-bark">{{ $peer->name }}</p>
 <p class="truncate text-sm text-fur">{{ $peer->username ?'@'.$peer->username :'Pet lover'}}</p>
 </div>
 </div>

 <x-ui.badge :variant="$canSend ?'success':'warning'" size="sm" dot>
 {{ $canSend ?'Online':'Restricted'}}
 </x-ui.badge>
 </div>
 </header>

 @if ($orderedMessages->isEmpty())
 <x-ui.empty-state
 icon="💌"
 title="Start the chat"
 description="Write a simple hello to begin."
 />
 @else
 <section
 x-data
 x-init="$nextTick(() => { if ($refs.log) { $refs.log.scrollTop = $refs.log.scrollHeight; } })"
 class="bg-gradient-to-b from-cream/60 via-warm-white to-cream/70"
 >
 <div x-ref="log" class="h-[58vh] min-h-[22rem] overflow-y-auto px-4 py-4 sm:px-5">
 <div class="space-y-3">
 @foreach ($orderedMessages as $message)
 @php
 $outgoing = (int) $message->sender_id === (int) auth()->id();
 $bubbleClasses = $outgoing
 ?'ml-auto bg-paw text-white rounded-2xl rounded-br-md shadow-button'
 :'mr-auto bg-warm-white text-bark border border-whisker/30 rounded-2xl rounded-bl-md shadow-sm';
 @endphp

 <article class="w-fit max-w-[88%] px-3.5 py-2.5 {{ $bubbleClasses }}">
 <p class="whitespace-pre-line text-sm leading-6">{{ $message->body ??'Message removed.'}}</p>

 <div class="mt-1.5 flex items-center gap-2 text-[11px] {{ $outgoing ?'text-white/85':'text-fur'}}">
 <time datetime="{{ optional($message->created_at)->toIso8601String() }}">
 {{ $message->created_at?->format('M j, g:i A') }}
 </time>

 @if ($outgoing)
 <span class="h-1 w-1 rounded-pill bg-white/80"></span>
 <form method="POST" action="{{ route('messages.destroy', $message) }}" onsubmit="return confirm('Delete this message?')">
 @csrf
 @method('DELETE')

 <button type="submit" class="font-medium underline decoration-dotted underline-offset-2 hover:no-underline">
 Delete
 </button>
 </form>
 @endif
 </div>
 </article>
 @endforeach
 </div>
 </div>
 </section>

 <div class="border-t border-whisker/25 bg-cream/50 px-4 py-3 sm:px-5">
 <x-ui.pagination :paginator="$messages" class="!mt-0 !border-t-0 !px-0 !py-0"/>
 </div>
 @endif

 @if ($canSend)
 <footer class="border-t border-whisker/30 bg-warm-white px-4 py-3 sm:px-5">
 <form method="POST" action="{{ route('messages.store', ['peer'=> $peer]) }}" class="space-y-3">
 @csrf

 @if ($activeListing)
 <input type="hidden" name="marketplace_listing_id" value="{{ $activeListing->getKey() }}">
 @endif

 <x-ui.textarea
 name="body"
 rows="2"
 maxlength="5000"
 required
 placeholder="Type a message..."
 :value="old('body')"
 :error="$errors->first('body')"
 />

 @if ($errors->has('marketplace_listing_id'))
 <x-ui.hint :error="$errors->first('marketplace_listing_id')"/>
 @endif

 <div class="flex items-center justify-between gap-3">
 <p class="text-xs text-fur">Simple, clear, respectful.</p>
 <x-ui.button type="submit" variant="primary" size="sm" icon="➤">
 Send
 </x-ui.button>
 </div>
 </form>
 </footer>
 @endif
 </x-ui.card>
 </div>
</x-app-layout>
