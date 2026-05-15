@props(['post','comment','currentReaction'=> null])

@php
    $reactionOptions = \App\Models\Content\Reaction::emojiMap();
@endphp

<div class="relative inline-flex items-center gap-2 group/react" x-data="{
 current: '{{ $currentReaction }}',
 total: {{ $comment->reactions_count }},
 showPicker: false,
 loading: false,
 async react(type) {
 if (this.loading) { return; }
 this.loading = true;
 const prev = this.current;
 const prevTotal = this.total;

 if (this.current === type) {
 this.total = Math.max(0, this.total - 1);
 this.current = null;
 } else {
 if (!this.current) {
 this.total += 1;
 }
 this.current = type;
 }

 this.showPicker = false;

 try {
 const response = await fetch('{{ route('comments.react', $comment) }}', {
 method:'POST',
 headers: {
'Content-Type':'application/json',
'X-CSRF-TOKEN':'{{ csrf_token() }}',
'Accept':'application/json',
 },
 body: JSON.stringify({ type }),
 });
 const data = await response.json();
 if (!response.ok || !data.success) {
 throw new Error('Reaction failed');
 }
 } catch (e) {
 this.current = prev;
 this.total = prevTotal;
 }

 this.loading = false;
 }
 }" @mouseleave="setTimeout(() => { if (!$el.matches(':hover')) showPicker = false }, 300)">
 <!-- Reaction Button -->
 <button @mouseenter="showPicker = true" @click="react(current ||'love')" class="hover:underline"
 :class="current ?'text-paw':''">
 <span x-show="!current">React</span>
 <span x-show="current ==='love'">Love</span>
 <span x-show="current ==='cute'">Cute</span>
 <span x-show="current ==='funny'">Funny</span>
 <span x-show="current ==='wow'">Wow</span>
 <span x-show="current ==='sad'">Sad</span>
 <span x-show="current ==='support'">Support</span>
 </button>

 <!-- Reaction Picker Popover -->
 <div x-show="showPicker" x-transition:enter="transition ease-out duration-100"
 x-transition:enter-start="opacity-0 translate-y-2 scale-95"
 x-transition:enter-end="opacity-100 translate-y-0 scale-100" x-transition:leave="transition ease-in duration-75"
 x-transition:leave-start="opacity-100 translate-y-0 scale-100"
 x-transition:leave-end="opacity-0 translate-y-2 scale-95"
 class="absolute bottom-6 -left-2 z-50 flex items-center gap-1 rounded-full border border-gray-200 bg-white p-1 shadow-lg"
 style="display: none;">
 @foreach($reactionOptions as $type => $emoji)
 <button type="button" title="{{ ucfirst($type) }}"
 class="h-8 w-8 rounded-full text-xl hover:scale-125 transition-transform origin-bottom"
 :class="current ==='{{ $type }}'?'bg-gray-100':''" @click="react('{{ $type }}')">{{ $emoji }}</button>
 @endforeach
 </div>
</div>
