<div
    class="rounded-[var(--radius-soft)] border border-fur/15 bg-cream p-3"
    x-data="{ cursorPosition: 0 }"
    x-on:mention-selected.window="$el.querySelector('[contenteditable]').textContent = $wire.content + '@' + $event.detail.username + ' '; $wire.set('content', $el.querySelector('[contenteditable]').textContent)"
>
    <div class="flex gap-3">
        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-paw-light text-xs font-bold text-paw-dark">
            {{ mb_substr(auth()->user()?->name ?? '?', 0, 1) }}
        </div>

        <div class="min-w-0 flex-1 space-y-2">
            <div class="relative">
                <div
                    contenteditable="true"
                    role="textbox"
                    aria-label="Write a reply"
                    x-init="$el.textContent = $wire.content"
                    x-on:input.debounce.250ms="cursorPosition = window.getSelection()?.focusOffset || 0; $wire.set('content', $event.target.textContent)"
                    class="min-h-16 rounded-[var(--radius-soft)] border border-fur/20 bg-warm-white px-3 py-2 text-sm leading-6 text-bark outline-none focus:border-paw focus:ring-2 focus:ring-paw/20"
                ></div>

                @if (count($mentionSuggestions) > 0)
                    <div class="absolute left-0 top-full z-30 mt-2 w-72 overflow-hidden rounded-[var(--radius-soft)] border border-fur/15 bg-warm-white shadow-lg">
                        @foreach ($mentionSuggestions as $suggestion)
                            <button type="button" wire:click="selectMention({{ $suggestion['id'] }}, '{{ $suggestion['username'] }}')" class="flex w-full items-center gap-2 px-3 py-2 text-left hover:bg-cream">
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

            <div class="flex items-center justify-end gap-3">
                <span class="text-xs text-fur">{{ strlen($content) }}/500</span>
                <button type="button" wire:click="submit" wire:loading.attr="disabled" wire:loading.class="opacity-50" class="rounded-full bg-paw px-4 py-2 text-sm font-bold text-white transition hover:bg-paw-dark disabled:cursor-not-allowed">
                    Reply
                </button>
            </div>
        </div>
    </div>
</div>
