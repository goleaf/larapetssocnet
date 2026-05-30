<div>
    @if ($isOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-bark/50 px-4 py-6">
            <div class="w-full max-w-lg rounded-[var(--radius-soft)] bg-warm-white p-5 shadow-xl">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-bold text-bark">Comment insights</h2>
                        <p class="mt-1 text-sm text-fur">Conversation activity on this post.</p>
                    </div>
                    <button type="button" wire:click="closeModal" class="rounded-full px-2 py-1 text-sm font-bold text-fur hover:bg-cream">X</button>
                </div>

                <dl class="mt-5 grid gap-3 sm:grid-cols-2">
                    <div class="rounded-[var(--radius-soft)] bg-cream p-3">
                        <dt class="text-xs font-bold uppercase tracking-wide text-fur">Total comments</dt>
                        <dd class="mt-1 text-2xl font-bold text-bark">{{ $insights['total_comments'] ?? 0 }}</dd>
                    </div>
                    <div class="rounded-[var(--radius-soft)] bg-cream p-3">
                        <dt class="text-xs font-bold uppercase tracking-wide text-fur">Most active commenter</dt>
                        <dd class="mt-1 text-sm font-bold text-bark">{{ $insights['most_active_commenter'] ?? 'No comments yet' }}</dd>
                    </div>
                    <div class="rounded-[var(--radius-soft)] bg-cream p-3">
                        <dt class="text-xs font-bold uppercase tracking-wide text-fur">Average length</dt>
                        <dd class="mt-1 text-sm font-bold text-bark">{{ $insights['average_length'] ?? 0 }} characters</dd>
                    </div>
                    <div class="rounded-[var(--radius-soft)] bg-cream p-3">
                        <dt class="text-xs font-bold uppercase tracking-wide text-fur">Most active time</dt>
                        <dd class="mt-1 text-sm font-bold text-bark">{{ $insights['active_day'] ?? 'No comments yet' }} at {{ $insights['active_hour'] ?? '' }}</dd>
                    </div>
                    <div class="rounded-[var(--radius-soft)] bg-cream p-3 sm:col-span-2">
                        <dt class="text-xs font-bold uppercase tracking-wide text-fur">Mentions generated</dt>
                        <dd class="mt-1 text-2xl font-bold text-bark">{{ $insights['mentions_total'] ?? 0 }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    @endif
</div>
