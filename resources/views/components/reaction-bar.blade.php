@props(['post','currentReaction'=> null])

<div
 x-data="{
 current:'{{ $currentReaction }}',
 counts: {
 love: {{ $post->reactions->where('type','love')->count() }},
 cute: {{ $post->reactions->where('type','cute')->count() }},
 funny: {{ $post->reactions->where('type','funny')->count() }},
 wow: {{ $post->reactions->where('type','wow')->count() }},
 sad: {{ $post->reactions->where('type','sad')->count() }},
 support: {{ $post->reactions->where('type','support')->count() }},
 },
 total: {{ $post->reactions_count }},
 showPicker: false,
 loading: false,
 async react(type) {
 if (this.loading) { return; }
 this.loading = true;
 const prev = this.current;
 const prevCounts = { ...this.counts };
 const prevTotal = this.total;

 if (this.current === type) {
 this.counts[type] = Math.max(0, this.counts[type] - 1);
 this.total = Math.max(0, this.total - 1);
 this.current = null;
 } else {
 if (this.current) {
 this.counts[this.current] = Math.max(0, this.counts[this.current] - 1);
 } else {
 this.total += 1;
 }
 this.counts[type] += 1;
 this.current = type;
 }

 this.showPicker = false;

 try {
 const response = await fetch('{{ route('posts.react', $post) }}', {
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
 this.counts = prevCounts;
 this.total = prevTotal;
 }

 this.loading = false;
 }
 }"
 class="relative flex items-center gap-2"
>
 <button type="button"class="rounded-md px-2 py-1 text-sm text-gray-600 hover:bg-gray-100"@click="react('love')">❤️ <span x-text="total"></span></button>
 <button type="button"class="rounded-md px-2 py-1 text-sm text-gray-600 hover:bg-gray-100"@click="showPicker = !showPicker">+</button>

 <div x-show="showPicker"@click.outside="showPicker = false"class="absolute bottom-9 left-0 z-10 rounded-full border border-gray-200 bg-white p-2 shadow"style="display: none;">
 <div class="flex items-center gap-1">
 <button type="button"class="h-8 w-8 rounded-full hover:bg-gray-100":class="current ==='love'?'ring-2 ring-emerald-500':''"@click="react('love')">❤️</button>
 <button type="button"class="h-8 w-8 rounded-full hover:bg-gray-100":class="current ==='cute'?'ring-2 ring-emerald-500':''"@click="react('cute')">🥰</button>
 <button type="button"class="h-8 w-8 rounded-full hover:bg-gray-100":class="current ==='funny'?'ring-2 ring-emerald-500':''"@click="react('funny')">😄</button>
 <button type="button"class="h-8 w-8 rounded-full hover:bg-gray-100":class="current ==='wow'?'ring-2 ring-emerald-500':''"@click="react('wow')">😮</button>
 <button type="button"class="h-8 w-8 rounded-full hover:bg-gray-100":class="current ==='sad'?'ring-2 ring-emerald-500':''"@click="react('sad')">😢</button>
 <button type="button"class="h-8 w-8 rounded-full hover:bg-gray-100":class="current ==='support'?'ring-2 ring-emerald-500':''"@click="react('support')">🤗</button>
 </div>
 </div>
</div>
