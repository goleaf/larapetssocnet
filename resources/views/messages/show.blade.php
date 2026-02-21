@section('title', 'Conversation')

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <x-avatar :src="$peer->avatar_url" :name="$peer->name" size="lg" />
                <div>
                    <p class="shell-kicker">Conversation</p>
                    <h1 class="shell-title text-xl">{{ $peer->name }}</h1>
                    <p class="text-sm shell-text-muted">{{ $peer->username ? '@'.$peer->username : 'Pet lover' }}</p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ route('messages.index') }}" class="btn-base btn-ghost px-3 py-2 text-sm">Inbox</a>
                <a href="{{ route('marketplace.index') }}" class="btn-base btn-ghost px-3 py-2 text-sm">Marketplace</a>
            </div>
        </div>
    </x-slot>

    <div class="space-y-4">
        @if ($activeListing)
            <section class="shell-panel p-4">
                <p class="shell-kicker">Listing Context</p>
                <p class="mt-1 text-sm" style="color: var(--ui-text);">
                    You are messaging about
                    <a href="{{ route('marketplace.show', $activeListing) }}" class="font-semibold hover:underline" style="color: var(--ui-primary);">{{ $activeListing->title }}</a>.
                </p>
            </section>
        @endif

        @if (! $canSend && $restriction)
            <x-flash-message type="warning" :message="$restriction" />
        @endif

        <section class="shell-card p-4 sm:p-5">
            @if ($orderedMessages->isEmpty())
                <x-empty-state
                    icon="🫶"
                    title="No messages yet"
                    description="Say hello to start the conversation."
                />
            @else
                <div class="space-y-3 scrollbar-subtle max-h-[32rem] overflow-y-auto pr-1">
                    @foreach ($orderedMessages as $message)
                        @php
                            $outgoing = (int) $message->sender_user_id === (int) auth()->id();
                        @endphp

                        <div class="flex {{ $outgoing ? 'justify-end' : 'justify-start' }}">
                            <div
                                class="max-w-[88%] rounded-2xl px-3.5 py-2.5 text-sm"
                                style="background: {{ $outgoing ? 'linear-gradient(135deg, var(--ui-primary), var(--ui-primary-strong))' : 'color-mix(in srgb, var(--ui-surface-muted) 78%, white 22%)' }}; color: {{ $outgoing ? '#f8fafc' : 'var(--ui-text)' }};"
                            >
                                <p class="whitespace-pre-line leading-6">{{ $message->body }}</p>

                                <div class="mt-1.5 flex items-center gap-2 text-[11px] {{ $outgoing ? 'text-emerald-100' : 'shell-text-muted' }}">
                                    <span>{{ $message->created_at?->format('M j, g:i A') }}</span>

                                    @if ($outgoing)
                                        <span class="dot-divider"></span>
                                        <form method="POST" action="{{ route('messages.destroy', $message) }}" onsubmit="return confirm('Delete this message?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="font-semibold underline decoration-dotted underline-offset-2 hover:no-underline">Delete</button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-4 shell-card-muted p-3">
                    {{ $messages->links() }}
                </div>
            @endif
        </section>

        @if ($canSend)
            <form method="POST" action="{{ route('messages.store', ['peer' => $peer]) }}" class="shell-panel p-4 sm:p-5">
                @csrf

                @if ($activeListing)
                    <input type="hidden" name="marketplace_listing_id" value="{{ $activeListing->getKey() }}">
                @endif

                <label for="body" class="shell-kicker">Reply</label>
                <textarea id="body" name="body" rows="4" required maxlength="5000" class="form-textarea mt-2">{{ old('body') }}</textarea>
                <x-input-error :messages="$errors->get('body')" class="mt-2" />
                <x-input-error :messages="$errors->get('marketplace_listing_id')" class="mt-2" />

                <div class="mt-3 flex justify-end">
                    <button type="submit" class="btn-base btn-primary px-4 py-2 text-sm">Send Message</button>
                </div>
            </form>
        @endif
    </div>
</x-app-layout>
