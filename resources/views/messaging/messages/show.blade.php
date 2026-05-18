@section('title', __('messages.show.title', ['name' => $peer->name]))

<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header
            :title="__('messages.show.heading')"
            :subtitle="__('messages.show.subtitle', ['name' => $peer->name])"
            :breadcrumbs="[
                ['label' => __('messages.index.heading'), 'href' => route('messages.index')],
                ['label' => $peer->name],
            ]"
            icon="💬"
        />
    </x-slot>

    <div class="w-full min-w-0 space-y-4" data-ui="messages-page">
        <x-ui.card padding="none" class="overflow-hidden">
            <header class="border-b border-whisker/30 bg-warm-white px-4 py-3 sm:px-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex min-w-0 items-center gap-3">
                        <x-user-avatar :user="$peer" size="md" />

                        <div class="min-w-0">
                            <p class="truncate text-base font-semibold text-bark">{{ $peer->name }}</p>
                            <p class="truncate text-sm text-fur">
                                {{ $peer->username ? '@'.$peer->username : __('messages.index.default_peer_label') }}
                            </p>
                        </div>
                    </div>

                    <x-ui.button variant="outline" size="sm" :href="route('messages.index')" icon="↩️" class="w-full sm:w-auto">
                        {{ __('messages.actions.back_to_inbox') }}
                    </x-ui.button>
                </div>
            </header>

            <section class="bg-[color:var(--surface-page)]">
                <div class="h-[58dvh] min-h-[22rem] overflow-y-auto px-4 py-4 sm:px-5">
                    <div class="space-y-3">
                        @forelse ($messages as $message)
                            <article class="flex {{ (int) $message->sender_id === (int) auth()->id() ? 'justify-end' : 'justify-start' }}">
                                <div class="w-fit max-w-[88%] rounded-2xl px-3.5 py-2.5 sm:max-w-xl {{ (int) $message->sender_id === (int) auth()->id() ? 'rounded-br-md bg-paw text-white shadow-button' : 'rounded-bl-md border border-whisker/30 bg-warm-white text-bark' }}">
                                    <p class="whitespace-pre-line break-words text-sm leading-6">{{ $message->body }}</p>
                                    <time
                                        datetime="{{ optional($message->created_at)->toIso8601String() }}"
                                        class="mt-1.5 block text-[11px] {{ (int) $message->sender_id === (int) auth()->id() ? 'text-white/85' : 'text-fur' }}"
                                    >
                                        {{ optional($message->created_at)->diffForHumans() }}
                                    </time>
                                </div>
                            </article>
                        @empty
                            <x-ui.empty-state
                                icon="💌"
                                :title="__('messages.show.empty_title')"
                                :description="__('messages.show.empty_description')"
                            />
                        @endforelse
                    </div>
                </div>
            </section>

            <div class="border-t border-whisker/25 bg-cream/50 px-4 py-3 sm:px-5">
                <x-ui.pagination :paginator="$messages" class="!mt-0 !border-t-0 !px-0 !py-0" />
            </div>

            <footer class="border-t border-whisker/30 bg-warm-white px-4 py-3 sm:px-5">
                <form method="POST" action="{{ route('messages.store', ['peer' => $peer]) }}" class="space-y-3">
                    @csrf

                    <x-ui.textarea
                        name="body"
                        rows="2"
                        maxlength="5000"
                        required
                        :placeholder="__('messages.show.input_placeholder')"
                        :value="old('body')"
                        :error="$errors->first('body')"
                    />

                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-xs text-fur">{{ __('messages.show.helper') }}</p>
                        <x-ui.button type="submit" variant="primary" size="sm" icon="➤" class="w-full sm:w-auto sm:min-w-28">
                            {{ __('messages.actions.send') }}
                        </x-ui.button>
                    </div>
                </form>
            </footer>
        </x-ui.card>
    </div>
</x-app-layout>
