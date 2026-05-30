<div
    class="rounded-[var(--radius-soft)] border border-fur/15 bg-warm-white p-4 shadow-sm"
    x-data="{ draftVisible: false, draftSavedAt: '', cursorPosition: 0 }"
    x-on:draft-found.window="draftVisible = true; draftSavedAt = $event.detail.savedAt"
    x-on:gif-selected.window="$wire.set('gifUrl', $event.detail.url)"
    x-on:mention-selected.window="$el.querySelector('[contenteditable]').textContent = $wire.content + '@' + $event.detail.username + ' '; $wire.set('content', $el.querySelector('[contenteditable]').textContent)"
>
    <div class="flex gap-3">
        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-paw-light text-sm font-bold text-paw-dark">
            {{ mb_substr(auth()->user()?->name ?? '?', 0, 1) }}
        </div>

        <div class="min-w-0 flex-1 space-y-3">
            <div x-cloak x-show="draftVisible" class="rounded-[var(--radius-soft)] border border-paw/20 bg-paw-light px-3 py-2 text-sm text-paw-dark">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span>Resume draft from <span x-text="draftSavedAt"></span></span>
                    <span class="flex gap-2">
                        <button type="button" x-on:click="draftVisible = false" class="font-bold text-paw-dark">Resume</button>
                        <button type="button" wire:click="discardDraft" x-on:click="draftVisible = false" class="font-bold text-fur">Discard</button>
                    </span>
                </div>
            </div>

            <div class="relative">
                <div
                    contenteditable="true"
                    role="textbox"
                    aria-label="Write a comment"
                    x-on:input.debounce.250ms="cursorPosition = window.getSelection()?.focusOffset || 0; $wire.set('content', $event.target.textContent)"
                    x-on:blur="$wire.autosaveDraft()"
                    class="min-h-24 rounded-[var(--radius-soft)] border border-fur/20 bg-cream px-3 py-2 text-sm leading-6 text-bark outline-none focus:border-paw focus:ring-2 focus:ring-paw/20"
                ></div>

                @if (count($mentionSuggestions) > 0)
                    <div class="absolute left-0 top-full z-30 mt-2 w-72 overflow-hidden rounded-[var(--radius-soft)] border border-fur/15 bg-warm-white shadow-lg">
                        @foreach ($mentionSuggestions as $suggestion)
                            <button type="button" wire:key="top-level-mention-{{ $suggestion['id'] }}" wire:click="selectMention({{ $suggestion['id'] }}, @js($suggestion['username']))" class="flex w-full items-center gap-2 px-3 py-2 text-left hover:bg-cream">
                                <span class="flex h-7 w-7 items-center justify-center overflow-hidden rounded-full bg-paw-light text-xs font-bold text-paw-dark">
                                    @if ($suggestion['avatar_url'])
                                        <img src="{{ $suggestion['avatar_url'] }}" alt="{{ $suggestion['name'] }}" class="h-full w-full object-cover">
                                    @else
                                        {{ mb_substr($suggestion['name'], 0, 1) }}
                                    @endif
                                </span>
                                <span class="min-w-0">
                                    <span class="block truncate text-sm font-bold text-bark">{{ $suggestion['name'] }}</span>
                                    <span class="block truncate text-xs text-fur">@{{ $suggestion['username'] }}</span>
                                </span>
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            @if ($gifUrl)
                <div class="relative max-w-[220px] overflow-hidden rounded-[var(--radius-soft)] border border-fur/15">
                    <img src="{{ $gifUrl }}" alt="Selected GIF" class="w-full object-cover">
                    <button type="button" wire:click="clearGifUrl" class="absolute right-2 top-2 rounded-full bg-bark/80 px-2 py-1 text-xs font-bold text-white">X</button>
                </div>
            @endif

            <div class="flex items-center justify-between gap-3">
                <button type="button" wire:click="openGifPicker" class="rounded-full border border-fur/20 px-3 py-1.5 text-xs font-bold text-fur transition hover:border-paw hover:text-paw">GIF</button>
                <div class="flex items-center gap-3">
                    <span class="text-xs text-fur">{{ strlen($content) }}/500</span>
                    <button type="button" wire:click="submit" wire:loading.attr="disabled" wire:loading.class="opacity-50" class="rounded-full bg-paw px-4 py-2 text-sm font-bold text-white transition hover:bg-paw-dark disabled:cursor-not-allowed">
                        Post
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div
        x-cloak
        x-data="{ open: false, query: '', results: [], loading: false, timer: null, search() { clearTimeout(this.timer); this.timer = setTimeout(async () => { if (this.query.trim().length < 1) { this.results = []; return; } this.loading = true; const response = await fetch('{{ route('comments.gifs.search') }}?q=' + encodeURIComponent(this.query)); const payload = await response.json(); this.results = payload.results || []; this.loading = false; }, 300) }, select(url) { window.dispatchEvent(new CustomEvent('gif-selected', { detail: { url } })); this.open = false; } }"
        x-on:open-comment-gif-picker.window="open = true"
        x-show="open"
        class="fixed inset-0 z-50 flex items-end bg-bark/50 sm:items-center sm:justify-center"
    >
        <div class="max-h-[85vh] w-full overflow-hidden rounded-t-[var(--radius-soft)] bg-warm-white p-4 shadow-xl sm:max-w-2xl sm:rounded-[var(--radius-soft)]">
            <div class="flex items-center justify-between gap-3">
                <input type="search" x-model="query" x-on:input="search" placeholder="Search GIFs" class="min-w-0 flex-1 rounded-full border border-fur/20 bg-cream px-4 py-2 text-sm text-bark focus:border-paw focus:outline-none focus:ring-2 focus:ring-paw/20">
                <button type="button" x-on:click="open = false" class="rounded-full px-3 py-2 text-sm font-bold text-fur hover:bg-cream">Close</button>
            </div>

            <div class="mt-4 max-h-[60vh] overflow-y-auto">
                <div x-show="loading" class="py-8 text-center text-sm text-fur">Searching...</div>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                    <template x-for="gif in results" :key="gif.id">
                        <button type="button" x-on:click="select(gif.gif_url)" class="overflow-hidden rounded-[var(--radius-soft)] border border-fur/15 bg-cream">
                            <img :src="gif.gif_preview_url || gif.gif_url" :alt="gif.title" class="h-28 w-full object-cover">
                        </button>
                    </template>
                </div>
            </div>
        </div>
    </div>
</div>
