<article id="post-{{ $post->id }}" class="shell-card hover-lift overflow-hidden p-4 sm:p-5" x-data="{
reaction: null,
likes: {{ (int) $post->likes_count }},
busy: false,
saved: {{ auth()->check() && ($post->saved_by_viewer ?? false) ? 'true' : 'false' }},
saveCount: {{ (int) ($post->save_count ?? 0) }},
saveBusy: false,
shares: {{ (int) ($post->shares_count ?? 0) }},
shareBusy: false,
shareCopied: false,
async react(type) {
 if (this.busy) return;
 this.busy = true;
 try {
 const res = await fetch(@js(route('posts.react', $post)), {
 method:'POST',
 headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ||''},
 body: JSON.stringify({ type })
 });
 if (!res.ok) throw new Error('reaction failed');
 const data = await res.json();
 if (data?.success) {
 this.reaction = data.data.current_reaction;
 this.likes = data.data.likes_count;
 }
 } finally { this.busy = false; }
},
async toggleSave() {
 if (this.saveBusy) return;
 this.saveBusy = true;
 const prevSaved = this.saved;
 const prevCount = this.saveCount;
 this.saved = !this.saved;
 this.saveCount = Math.max(0, this.saveCount + (this.saved ? 1 : -1));
 try {
 const res = await fetch(@js(route('posts.save', $post)), {
 method:'POST',
 headers: {'Accept':'application/json','X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ||''},
 });
 if (!res.ok) throw new Error('save failed');
 const data = await res.json();
 if (typeof data.saved === 'boolean') {
 this.saved = data.saved;
 }
 } catch (_) {
 this.saved = prevSaved;
 this.saveCount = prevCount;
 } finally { this.saveBusy = false; }
},
async sharePost() {
 if (this.shareBusy) return;
 this.shareBusy = true;
 const prevShares = this.shares;
 try {
 const res = await fetch(@js(route('posts.share', $post)), {
 method:'POST',
 headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ||''},
 body: JSON.stringify({ method: 'copy_link' })
 });
 if (!res.ok) throw new Error('share failed');
 const data = await res.json();
 if (typeof data.shares_count === 'number') {
 this.shares = data.shares_count;
 }
 const link = data.url || @js(route('posts.show', $post));
 if (navigator.clipboard?.writeText) {
 await navigator.clipboard.writeText(link);
 }
 this.shareCopied = true;
 setTimeout(() => { this.shareCopied = false; }, 1800);
 } catch (_) {
 this.shares = prevShares;
 } finally { this.shareBusy = false; }
}
}">
 <header class="flex items-start justify-between gap-4">
 <div class="min-w-0 flex items-start gap-3">
 <a href="{{ route('profile.show', ['user' => $post->displayAuthor()]) }}" class="shrink-0">
 <x-avatar :src="$post->displayAuthor()?->avatar_url" :name="$post->displayAuthor()?->name" size="md"/>
 </a>

 <div class="min-w-0">
 <a href="{{ route('profile.show', ['user' => $post->displayAuthor()]) }}" class="truncate text-sm font-semibold hover:underline" style="color: var(--ui-text);">
 {{ $post->displayAuthor()?->name ??'Pet Lover'}}
 </a>

 <p class="truncate text-xs shell-text-muted">
 <span>{{ $post->displayAuthor()?->username ?'@'.$post->displayAuthor()?->username :'community-member'}}</span>
 <span class="dot-divider"></span>
 @php($displayTime = $post->published_at ?? $post->created_at)
 <span>{{ $displayTime?->diffForHumans() }}</span>
 <span class="dot-divider"></span>
 <span>{{ $post->visibilityLabel() }}</span>
 @if ($post->is_pinned)
 <span class="dot-divider"></span>
 <span class="font-semibold" style="color: var(--ui-secondary);">Pinned</span>
 @endif
 @php($statusValue = $post->status?->value ?? (string) $post->status)
 @if ($statusValue !== 'published' && (int) auth()->id() === (int) $post->user_id)
 <span class="dot-divider"></span>
 <span class="font-semibold" style="color: var(--ui-secondary);">{{ ucfirst($statusValue) }}</span>
 @endif
 </p>
 </div>
 </div>

 <a href="{{ route('posts.show', $post) }}" class="btn-base btn-ghost px-2.5 py-1.5 text-xs">Open</a>
 </header>

@if (filled($post->body_html ?? $post->body))
 <div class="mt-3 whitespace-pre-line text-sm leading-6" style="color: var(--ui-text);">
 {!! $post->body_html ?? e((string) $post->body) !!}
 </div>
@endif

 @if ($post->hashtags->isNotEmpty())
 <div class="mt-3 flex flex-wrap gap-2">
 @foreach ($post->hashtags as $hashtag)
 <a href="{{ route('hashtags.show', $hashtag) }}" class="chip hover-lift" style="color: var(--ui-primary); border-color: color-mix(in srgb, var(--ui-primary) 36%, var(--ui-border) 64%);">
 #{{ $hashtag->name }}
 </a>
 @endforeach
 </div>
 @endif

 @if ($post->location)
 <p class="mt-3 inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs shell-text-muted" style="border-color: var(--ui-border);">
 <span aria-hidden="true">📍</span>
 <span>{{ $post->location }}</span>
 </p>
 @endif

 @if ($post->displayPhotos()->isNotEmpty())
 <div class="mt-4 grid gap-2 {{ $post->displayPhotos()->count() === 1 ?'grid-cols-1':'grid-cols-2'}}">
 @foreach ($post->displayPhotos()->take(4) as $photo)
 <div class="relative overflow-hidden rounded-xl border" style="border-color: var(--ui-border);">
 <img src="{{ $photo->getUrl() }}" alt="Post photo" class="h-56 w-full object-cover" loading="lazy">

 @if ($loop->last && $post->displayPhotos()->count() > 4)
 <div class="absolute inset-0 grid place-content-center bg-slate-950/45 text-xl font-bold text-white">
 +{{ $post->displayPhotos()->count() - 4 }}
 </div>
 @endif
 </div>
 @endforeach
 </div>
 @endif

 @if ($post->displayVideo())
 <div class="mt-4 overflow-hidden rounded-xl border" style="border-color: var(--ui-border);">
 <video class="w-full" controls preload="metadata">
 <source src="{{ $post->displayVideo()?->getUrl() }}" type="{{ $post->displayVideo()?->mime_type }}">
 </video>
 </div>
 @endif

 <footer class="mt-4">
 <div class="mb-2 flex items-center gap-2 text-xs shell-text-muted">
 <span><span x-text="new Intl.NumberFormat().format(likes)">{{ number_format((int) $post->likes_count) }}</span> reactions</span>
 <span class="dot-divider"></span>
 <span>{{ number_format((int) $post->comments_count) }} comments</span>
 <span class="dot-divider"></span>
 <span class="chip">{{ strtoupper((string) $post->type) }}</span>
 </div>

 @auth
 <div class="mb-3 flex flex-wrap gap-1.5">
 @foreach (\App\Models\Post::reactionEmojiMap() as $type => $emoji)
 <button
 type="button"
 @click="react('{{ $type }}')"
 :disabled="busy"
 class="btn-base btn-ghost px-2.5 py-1 text-xs"
 :class="{'ring-2 ring-emerald-500': reaction ==='{{ $type }}'}"
 aria-label="React {{ $type }}"
 >
 {{ $emoji }} {{ ucfirst($type) }}
 </button>
 @endforeach
 </div>
 @endauth

 <div class="flex flex-wrap items-center gap-2">
 <a href="{{ route('posts.show', $post) }}" class="btn-base btn-ghost px-3 py-2 text-xs">View Thread</a>
 <a href="{{ route('posts.show', $post) }}#comments" class="btn-base btn-ghost px-3 py-2 text-xs">Comment</a>
 <a href="{{ route('posts.show', $post) }}" class="btn-base btn-secondary px-3 py-2 text-xs">React</a>
 @auth
 <button type="button" @click="toggleSave()" class="btn-base btn-ghost px-3 py-2 text-xs" :class="{'btn-secondary': saved }">
 <span x-text="saved ?'Saved':'Save'"></span>
 <span class="opacity-70" x-text="saveCount"></span>
 </button>
 <button type="button" @click="sharePost()" class="btn-base btn-ghost px-3 py-2 text-xs" :class="{'btn-secondary': shareCopied }">
 <span x-text="shareCopied ?'Link Copied':'Share'"></span>
 <span class="opacity-70" x-text="shares"></span>
 </button>
 @if(auth()->check() && auth()->id() !== $post->user_id)
 <form method="POST" action="{{ route('posts.report', $post) }}" class="inline" onsubmit="return confirm('Report this post?');">
 @csrf
 <input type="hidden" name="reason" value="spam">
 <button type="submit" class="btn-base btn-ghost px-3 py-2 text-xs">Report</button>
 </form>
 @endif
 @endauth
 </div>
 </footer>
</article>
