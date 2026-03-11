@php
 /** @var \App\Models\Post $post */

 $author = $post->user;
 $photos = collect($post->getMedia('photos'))->merge($post->getMedia('images'));
 $video = $post->getFirstMedia('video');
 $visibilityLabel = ucfirst((string) ($post->visibility ?:'public'));
 $reactionTypes = [
'love'=>'❤️',
'cute'=>'🥹',
'funny'=>'😂',
'wow'=>'😮',
'sad'=>'😢',
'support'=>'🤝',
 ];
@endphp

<article id="post-{{ $post->id }}" class="shell-card hover-lift overflow-hidden p-4 sm:p-5" x-data="{ reaction: null, likes: {{ (int) $post->likes_count }}, busy: false, shareCopied: false, async react(type) { if (this.busy) return; this.busy = true; try { const res = await fetch(@js(route('posts.react', $post)), { method:'POST', headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ||''}, body: JSON.stringify({ type }) }); if (!res.ok) throw new Error('reaction failed'); const data = await res.json(); if (data?.success) { this.reaction = data.data.current_reaction; this.likes = data.data.likes_count; } } finally { this.busy = false; } }, async sharePost() { const link = @js(route('posts.show', $post)); try { if (navigator.clipboard?.writeText) { await navigator.clipboard.writeText(link); } else { const input = document.createElement('input'); input.value = link; document.body.appendChild(input); input.select(); document.execCommand('copy'); document.body.removeChild(input); } this.shareCopied = true; setTimeout(() => { this.shareCopied = false; }, 1800); } catch (_) {} } }">
 <header class="flex items-start justify-between gap-4">
 <div class="min-w-0 flex items-start gap-3">
 <a href="{{ route('profile.show', ['user'=> $author]) }}" class="shrink-0">
 <x-avatar :src="$author?->avatar_url" :name="$author?->name" size="md" />
 </a>

 <div class="min-w-0">
 <a href="{{ route('profile.show', ['user'=> $author]) }}" class="truncate text-sm font-semibold hover:underline" style="color: var(--ui-text);">
 {{ $author?->name ??'Pet Lover'}}
 </a>

 <p class="truncate text-xs shell-text-muted">
 <span>{{ $author?->username ?'@'.$author->username :'community-member'}}</span>
 <span class="dot-divider"></span>
 <span>{{ $post->created_at?->diffForHumans() }}</span>
 <span class="dot-divider"></span>
 <span>{{ $visibilityLabel }}</span>
 @if ($post->is_pinned)
 <span class="dot-divider"></span>
 <span class="font-semibold" style="color: var(--ui-secondary);">Pinned</span>
 @endif
 </p>
 </div>
 </div>

 <a href="{{ route('posts.show', $post) }}" class="btn-base btn-ghost px-2.5 py-1.5 text-xs">Open</a>
 </header>

 @if (filled($post->body))
 <p class="mt-3 whitespace-pre-line text-sm leading-6" style="color: var(--ui-text);">{{ $post->body }}</p>
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

 @if ($photos->isNotEmpty())
 @php
 $photoCount = $photos->count();
 @endphp

 <div class="mt-4 grid gap-2 {{ $photoCount === 1 ?'grid-cols-1':'grid-cols-2'}}">
 @foreach ($photos->take(4) as $photo)
 <div class="relative overflow-hidden rounded-xl border" style="border-color: var(--ui-border);">
 <img src="{{ $photo->getUrl() }}" alt="Post photo" class="h-56 w-full object-cover" loading="lazy">

 @if ($loop->last && $photoCount > 4)
 <div class="absolute inset-0 grid place-content-center bg-slate-950/45 text-xl font-bold text-white">
 +{{ $photoCount - 4 }}
 </div>
 @endif
 </div>
 @endforeach
 </div>
 @endif

 @if ($video)
 <div class="mt-4 overflow-hidden rounded-xl border" style="border-color: var(--ui-border);">
 <video class="w-full"controls preload="metadata">
 <source src="{{ $video->getUrl() }}" type="{{ $video->mime_type }}">
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
 @foreach ($reactionTypes as $type => $emoji)
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
 <button type="button" @click="sharePost()" class="btn-base btn-ghost px-3 py-2 text-xs" :class="{'btn-secondary': shareCopied }">
 <span x-text="shareCopied ?'Link Copied':'Share'"></span>
 </button>
 </div>
 </footer>
</article>
