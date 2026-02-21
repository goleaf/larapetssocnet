<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-semibold text-gray-800 leading-tight">Conversation</h2>
                <p class="mt-1 text-sm text-gray-600">{{ $peer->name }}{{ $peer->username ? ' (@'.$peer->username.')' : '' }}</p>
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ route('messages.index') }}" class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Back to Inbox</a>
                <a href="{{ route('marketplace.index') }}" class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Marketplace</a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-4xl space-y-4 px-4 sm:px-6 lg:px-8">
            @if ($activeListing)
                <div class="rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm">
                    <p class="font-semibold text-blue-900">Listing context</p>
                    <p class="mt-1 text-blue-800">
                        You are messaging about
                        <a href="{{ route('marketplace.show', $activeListing) }}" class="font-semibold underline hover:no-underline">{{ $activeListing->title }}</a>.
                    </p>
                </div>
            @endif

            @if (! $canSend && $restriction)
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                    {{ $restriction }}
                </div>
            @endif

            <div class="space-y-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                @if ($orderedMessages->isEmpty())
                    <p class="rounded-lg border border-dashed border-gray-300 p-6 text-center text-sm text-gray-600">No messages yet.</p>
                @else
                    @foreach ($orderedMessages as $message)
                        @php
                            $outgoing = (int) $message->sender_user_id === (int) auth()->id();
                        @endphp

                        <div class="flex {{ $outgoing ? 'justify-end' : 'justify-start' }}">
                            <div class="max-w-[85%] space-y-1 rounded-xl px-3 py-2 text-sm {{ $outgoing ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-900' }}">
                                <p class="whitespace-pre-line">{{ $message->body }}</p>

                                <div class="flex items-center gap-2 text-[11px] {{ $outgoing ? 'text-blue-100' : 'text-gray-500' }}">
                                    <span>{{ $message->created_at?->format('M j, Y g:i A') }}</span>

                                    @if ($outgoing)
                                        <form method="POST" action="{{ route('messages.destroy', $message) }}" onsubmit="return confirm('Delete this message?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="font-semibold underline hover:no-underline">Delete</button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach

                    <div>
                        {{ $messages->links() }}
                    </div>
                @endif
            </div>

            @if ($canSend)
                <form method="POST" action="{{ route('messages.store', ['peer' => $peer]) }}" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                    @csrf

                    @if ($activeListing)
                        <input type="hidden" name="marketplace_listing_id" value="{{ $activeListing->getKey() }}">
                    @endif

                    <label for="body" class="block text-sm font-medium text-gray-700">Message</label>
                    <textarea id="body" name="body" rows="4" required maxlength="5000"
                        class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                    >{{ old('body') }}</textarea>
                    <x-input-error :messages="$errors->get('body')" class="mt-2" />
                    <x-input-error :messages="$errors->get('marketplace_listing_id')" class="mt-2" />

                    <div class="mt-3 flex justify-end">
                        <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Send Message</button>
                    </div>
                </form>
            @endif
        </div>
    </div>
</x-app-layout>
