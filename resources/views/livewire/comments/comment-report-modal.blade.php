<div>
    @if ($isOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-bark/50 px-4 py-6">
            <div class="w-full max-w-md rounded-[var(--radius-soft)] bg-warm-white p-5 shadow-xl">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-bold text-bark">Report comment</h2>
                        <p class="mt-1 text-sm text-fur">Choose the reason that best matches the issue.</p>
                    </div>
                    <button type="button" wire:click="closeModal" class="rounded-full px-2 py-1 text-sm font-bold text-fur hover:bg-cream">X</button>
                </div>

                <div class="mt-4 space-y-2">
                    @foreach (self::REASONS as $reason => $label)
                        <button type="button" wire:click="selectReason('{{ $reason }}')" class="flex w-full items-center gap-3 rounded-[var(--radius-soft)] border px-3 py-2 text-left text-sm transition @if ($selectedReason === $reason) border-paw bg-paw-light text-paw-dark @else border-fur/15 text-bark hover:bg-cream @endif">
                            <span class="h-3 w-3 rounded-full border @if ($selectedReason === $reason) border-paw bg-paw @else border-fur/40 @endif"></span>
                            <span>{{ $label }}</span>
                        </button>
                    @endforeach
                </div>

                @if ($selectedReason === 'other' || $selectedReason !== null)
                    <label class="mt-4 block">
                        <span class="text-sm font-semibold text-bark">Additional context</span>
                        <textarea wire:model.live="additionalContext" rows="4" class="mt-2 w-full rounded-[var(--radius-soft)] border border-fur/20 bg-cream px-3 py-2 text-sm text-bark focus:border-paw focus:outline-none focus:ring-2 focus:ring-paw/20"></textarea>
                    </label>
                @endif

                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" wire:click="closeModal" class="rounded-full px-4 py-2 text-sm font-bold text-fur hover:bg-cream">Cancel</button>
                    <button type="button" wire:click="submit" wire:loading.attr="disabled" wire:loading.class="opacity-50" class="rounded-full bg-paw px-4 py-2 text-sm font-bold text-white hover:bg-paw-dark disabled:cursor-not-allowed">
                        Submit report
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
