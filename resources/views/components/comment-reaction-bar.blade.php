@props(['post', 'comment', 'currentReaction' => null, 'livewire' => false])

@php
    $options = collect(\App\Models\Content\Reaction::options())
        ->whereIn('type', \App\Models\Content\Reaction::commentTypes())
        ->values()
        ->all();
    $counts = [
        'paw' => (int) ($comment->paw_count ?? 0),
        'love' => (int) ($comment->love_count ?? 0),
    ];
    $currentReaction = filled($currentReaction)
        ? \App\Models\Content\Reaction::normalizeType((string) $currentReaction)
        : null;
@endphp

<div
    class="inline-flex items-center gap-1"
    x-data="{
        current: @js($currentReaction),
        counts: @js($counts),
        loading: false,
        async react(type) {
            if (this.loading) {
                return
            }

            this.loading = true
            const previousCurrent = this.current
            const previousCounts = { ...this.counts }
            const removing = this.current === type

            if (removing) {
                this.counts[type] = Math.max(0, (this.counts[type] || 0) - 1)
                this.current = null
            } else {
                if (this.current) {
                    this.counts[this.current] = Math.max(0, (this.counts[this.current] || 0) - 1)
                }

                this.counts[type] = (this.counts[type] || 0) + 1
                this.current = type
            }

            try {
                const data = @js((bool) $livewire)
                    ? await $wire.reactToComment(@js((int) $comment->id), type)
                    : await this.reactViaFetch(type)

                if (!data?.success) {
                    throw new Error('reaction_failed')
                }

                this.reconcile(data.data || {})
            } catch {
                this.current = previousCurrent
                this.counts = previousCounts
            } finally {
                this.loading = false
            }
        },
        async reactViaFetch(type) {
            const response = await fetch(@js(route('comments.react', $comment)), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ type }),
                })
            const data = await response.json()

            if (!response.ok || !data.success) {
                throw new Error('reaction_failed')
            }

            return data
        },
        reconcile(data) {
            if (data?.current_reaction === null || typeof data?.current_reaction === 'string') {
                this.current = data.current_reaction
            }

            if (data?.reaction_counts) {
                this.counts = {
                    paw: Number(data.reaction_counts.paw || 0),
                    love: Number(data.reaction_counts.love || 0),
                }
            }
        },
    }"
    data-ui="comment-reaction-bar"
>
    @foreach ($options as $option)
        <button
            type="button"
            class="inline-flex min-h-8 items-center gap-1 rounded-[var(--radius-pill)] border px-2 text-xs font-semibold transition hover:bg-cream focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw"
            :class="current === @js($option['type']) ? @js($option['button_class']) : 'border-transparent text-fur'"
            :aria-pressed="current === @js($option['type'])"
            x-bind:disabled="loading"
            x-bind:aria-busy="loading"
            @click="react(@js($option['type']))"
            data-ui="comment-reaction-{{ $option['type'] }}"
        >
            <span aria-hidden="true">{{ $option['emoji'] }}</span>
            <span>{{ $option['label'] }}</span>
            <span class="opacity-80" x-text="counts[@js($option['type'])] || 0">{{ $counts[$option['type']] ?? 0 }}</span>
        </button>
    @endforeach
</div>
