@props([
    'likes' => 0,
    'comments' => 0,
    'shares' => 0,
    'saves' => 0,
    'reacted' => false,
    'saved' => false,
])

<div {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-2']) }}>
    <button
        type="button"
        class="btn-base btn-ghost px-3 py-2 text-sm"
        x-data="reactionState(@js($reacted), {{ (int) $likes }})"
        @click="toggle()"
        :class="reacted ? 'btn-primary text-white' : 'btn-ghost'"
    >
        <span aria-hidden="true">💚</span>
        <span x-text="window.uiHelpers.formatCount(count)"></span>
    </button>

    <button type="button" class="btn-base btn-ghost px-3 py-2 text-sm">
        <span aria-hidden="true">💬</span>
        <span>{{ number_format((int) $comments) }}</span>
    </button>

    <button type="button" class="btn-base btn-ghost px-3 py-2 text-sm">
        <span aria-hidden="true">🔁</span>
        <span>{{ number_format((int) $shares) }}</span>
    </button>

    <button
        type="button"
        class="btn-base btn-ghost px-3 py-2 text-sm"
        x-data="saveState(@js($saved))"
        @click="toggle()"
        :class="saved ? 'btn-secondary' : 'btn-ghost'"
    >
        <span aria-hidden="true">🔖</span>
        <span>{{ number_format((int) $saves) }}</span>
    </button>
</div>
