@section('title', 'Follow Requests')

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="shell-title text-xl">Follow Requests</h1>
                <p class="mt-1 text-sm shell-text-muted">Review pending requests for your private profile.</p>
            </div>
            <button
                type="button"
                class="btn-base btn-primary px-3 py-2 text-xs"
                x-data
                @click="fetch('{{ route('follow-requests.approve-all') }}', { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' } }).then(() => window.location.reload())"
            >
                Approve all
            </button>
        </div>
    </x-slot>

    <section class="shell-card p-5 dark:border-slate-700/60 dark:bg-slate-900/40">
        <div class="space-y-3">
            @forelse ($requests as $requester)
                <article class="flex items-center gap-3 rounded-xl border border-slate-200 p-3 dark:border-slate-700">
                    <x-avatar :user="$requester" size="md" />
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-semibold">{{ $requester->name }}</p>
                        <p class="text-xs shell-text-muted">@{{ $requester->username }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            class="btn-base btn-primary px-3 py-2 text-xs"
                            type="button"
                            x-data
                            @click="fetch('/follow-requests/{{ $requester->username }}/approve', { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' } }).then(() => window.location.reload())"
                        >Approve</button>
                        <button
                            class="btn-base btn-ghost px-3 py-2 text-xs"
                            type="button"
                            x-data
                            @click="fetch('/follow-requests/{{ $requester->username }}/reject', { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' } }).then(() => window.location.reload())"
                        >Reject</button>
                    </div>
                </article>
            @empty
                <x-empty-state icon="mail" title="No pending requests" description="You're all caught up." />
            @endforelse
        </div>

        <div class="mt-4">
            {{ $requests->links() }}
        </div>
    </section>
</x-app-layout>
