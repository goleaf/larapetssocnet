<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-semibold text-gray-800 leading-tight">{{ $listing->title }}</h2>
                <p class="mt-1 text-sm text-gray-600">{{ ucfirst($listing->listing_type ?: 'listing') }} ·
                    {{ ucfirst($listing->status) }}</p>
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ route('marketplace.index') }}"
                    class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Back
                    to Marketplace</a>

                @if ($canManage)
                    <a href="{{ route('marketplace.edit', $listing) }}"
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Edit</a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto grid max-w-7xl gap-6 px-4 sm:px-6 lg:grid-cols-[20rem_minmax(0,1fr)] lg:px-8">
            <div class="space-y-6 lg:col-start-2 lg:row-start-1">
                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                    @if ($listing->cover_photo_url)
                        <img src="{{ $listing->cover_photo_url }}" alt="{{ $listing->title }}"
                            class="h-[320px] w-full object-cover">
                    @else
                        <div class="flex h-[320px] items-center justify-center text-5xl text-gray-400">🛍️</div>
                    @endif
                </div>

                @if ($gallery->isNotEmpty())
                    <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                        @foreach ($gallery as $media)
                            <a href="{{ $media->getUrl() }}" target="_blank" rel="noreferrer"
                                class="overflow-hidden rounded-lg border border-gray-200">
                                <img src="{{ $media->getUrl() }}" alt="Listing image" class="h-24 w-full object-cover md:h-28">
                            </a>
                        @endforeach
                    </div>
                @endif

                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                    <p class="text-2xl font-bold text-blue-700">{{ $listing->formatted_price ?: 'Price on request' }}
                    </p>

                    <dl class="mt-4 grid gap-3 text-sm text-gray-700 sm:grid-cols-2">
                        <div>
                            <dt class="font-semibold text-gray-500">Location</dt>
                            <dd>{{ $listing->location_text ?: 'Not specified' }}</dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-gray-500">Views</dt>
                            <dd>{{ number_format((int) $listing->views_count) }}</dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-gray-500">Contact Phone</dt>
                            <dd>{{ $listing->contact_phone ?: 'Not provided' }}</dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-gray-500">Contact Email</dt>
                            <dd>{{ $listing->contact_email ?: 'Not provided' }}</dd>
                        </div>
                    </dl>

                    <div class="mt-5 prose max-w-none text-sm text-gray-800">
                        {!! nl2br(e($listing->description)) !!}
                    </div>
                </div>
            </div>

            <aside class="space-y-4 lg:col-start-1 lg:row-start-1 lg:sticky lg:top-24 lg:self-start">
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Seller</p>
                    <div class="mt-3 flex items-center gap-3">
                        <x-avatar :src="$listing->seller?->avatar_url" :name="$listing->seller?->name" size="md" />
                        <div>
                            <p class="font-semibold text-gray-900">{{ $listing->seller?->name ?: 'Unknown seller' }}</p>
                            @if ($listing->seller?->username)
                                <a href="{{ route('profile.show', ['user' => $listing->seller->username]) }}"
                                    class="text-sm text-blue-600 hover:underline">{{ '@' . $listing->seller->username }}</a>
                            @endif
                        </div>
                    </div>

                    @auth
                        @if ($canManage)
                            <div class="mt-4 space-y-2">
                                <a href="{{ route('marketplace.edit', $listing) }}"
                                    class="inline-flex w-full items-center justify-center rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Edit
                                    Listing</a>

                                <form method="POST" action="{{ route('marketplace.destroy', $listing) }}"
                                    onsubmit="return confirm('Delete this listing?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                        class="inline-flex w-full items-center justify-center rounded-lg bg-red-600 px-3 py-2 text-sm font-semibold text-white hover:bg-red-700">Delete
                                        Listing</button>
                                </form>
                            </div>
                        @elseif ($canContactSeller)
                            <form method="POST" action="{{ route('marketplace.contact', $listing) }}" class="mt-4">
                                @csrf
                                <button type="submit"
                                    class="inline-flex w-full items-center justify-center rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700">Contact
                                    Seller</button>
                            </form>
                        @else
                            <p class="mt-4 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800">{{ $contactRestriction }}
                            </p>
                        @endif
                    @else
                        <a href="{{ route('login') }}"
                            class="mt-4 inline-flex w-full items-center justify-center rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700">Sign
                            In to Contact Seller</a>
                    @endauth
                </div>
            </aside>
        </div>
    </div>
</x-app-layout>