<div class="inline-flex items-center gap-1 rounded-full bg-cream p-1">
    <button type="button" wire:click="react('paw')" class="inline-flex items-center gap-1 rounded-full px-2 py-1 text-xs font-bold transition @if ($currentReaction === 'paw') bg-paw text-white @else text-fur hover:bg-warm-white hover:text-paw @endif">
        <span>Paw</span>
        <span>{{ $pawCount }}</span>
    </button>

    <button type="button" wire:click="react('love')" class="inline-flex items-center gap-1 rounded-full px-2 py-1 text-xs font-bold transition @if ($currentReaction === 'love') bg-paw text-white @else text-fur hover:bg-warm-white hover:text-paw @endif">
        <span>Love</span>
        <span>{{ $loveCount }}</span>
    </button>
</div>
