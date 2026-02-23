@php
    use Illuminate\Support\Carbon;

    $petSlug = $pet->slug ?? $pet->getKey();

    $personalityTags = $pet->personality_tags ?? [];

    if (is_string($personalityTags)) {
        $decoded = json_decode($personalityTags, true);
        $personalityTags = is_array($decoded) ? $decoded : explode(',', $personalityTags);
    }

    $birthdate = data_get($pet, 'birth_date') ?? data_get($pet, 'birthdate');
    $birthdateLabel = null;
    $ageLabel = data_get($pet, 'age_formatted');

    if ($birthdate instanceof \Illuminate\Support\CarbonInterface) {
        $birthdateLabel = $birthdate->toFormattedDateString();
    } elseif (is_string($birthdate) && $birthdate !== '') {
        try {
            $birthdateLabel = Carbon::parse($birthdate)->toFormattedDateString();
        } catch (Throwable) {
            $birthdateLabel = $birthdate;
        }
    }

    if (!$ageLabel && $birthdateLabel) {
        try {
            $years = Carbon::parse((string) $birthdate)->age;
            $ageLabel = $years . 'years';
        } catch (Throwable) {
            $ageLabel = null;
        }
    }

    $followerCount = method_exists($pet, 'followers') ? $pet->followers()->count() : null;
    $isFollowing = false;

    if (auth()->check() && !$isOwner && method_exists($pet, 'followers')) {
        $isFollowing = $pet->followers()->whereKey(auth()->id())->exists();
    }
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $pet->name ?? 'Pet profile'}}
            </h2>

            <div class="flex items-center gap-3">
                @if($isOwner)
                    <a href="{{ route('pets.edit', $petSlug) }}" class="text-sm text-indigo-600 hover:text-indigo-800">Edit
                        profile</a>
                    <a href="{{ route('pets.health.index', $petSlug) }}"
                        class="text-sm text-indigo-600 hover:text-indigo-800">Health logs</a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-700">
                    {{ session('status') }}
                </div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <div class="flex flex-wrap items-start justify-between gap-6">
                    <div class="space-y-2">
                        <div class="text-sm text-gray-500">{{ $pet->species ?? 'Unknown species'}}
                            @if(!empty($pet->breed)) • {{ $pet->breed }} @endif</div>
                        @if($ageLabel)
                            <div class="text-sm text-gray-500">Age: {{ $ageLabel }}</div>
                        @endif
                        <div class="text-sm text-gray-600">{{ $pet->bio ?: 'No bio yet.'}}</div>
                        <div class="flex flex-wrap gap-2">
                            @if(!empty($pet->is_adoptable) || !empty($pet->is_for_adoption))
                                <span
                                    class="inline-flex rounded-full bg-emerald-100 px-3 py-1 text-xs font-medium text-emerald-700">Up
                                    for adoption</span>
                            @endif
                            @if(!empty($pet->is_public))
                                <span
                                    class="inline-flex rounded-full bg-blue-100 px-3 py-1 text-xs font-medium text-blue-700">Public</span>
                            @endif
                            @if(!$isOwner && !empty($pet->is_public))
                                <span
                                    class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-700">Visible
                                    profile</span>
                            @endif
                        </div>
                    </div>

                    @auth
                        @if(!$isOwner)
                            <div class="text-right space-y-2">
                                <button id="follow-toggle" type="button"
                                    class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700"
                                    data-follow-url="{{ route('pets.follow', $petSlug) }}"
                                    data-unfollow-url="{{ route('pets.unfollow', $petSlug) }}"
                                    data-following="{{ $isFollowing ? '1' : '0'}}">
                                    {{ $isFollowing ? 'Unfollow' : 'Follow'}}
                                </button>

                                @if($followerCount !== null)
                                    <p class="text-xs text-gray-500"><span id="followers-count">{{ $followerCount }}</span>
                                        followers</p>
                                @endif
                            </div>
                        @endif
                    @endauth
                </div>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="border-b border-gray-200 px-6 py-4">
                    <nav class="flex flex-wrap gap-2">
                        @foreach (['posts' => 'Posts', 'gallery' => 'Gallery', 'about' => 'About'] as $tab => $label)
                            <a href="{{ route('pets.show', ['slug' => $petSlug, 'tab' => $tab]) }}"
                                class="rounded-md px-3 py-2 text-sm {{ $activeTab === $tab ? 'bg-indigo-100 text-indigo-700' : 'text-gray-600 hover:bg-gray-100'}}">
                                {{ $label }}
                            </a>
                        @endforeach

                        @if($isOwner)
                            <a href="{{ route('pets.show', ['slug' => $petSlug, 'tab' => 'health']) }}"
                                class="rounded-md px-3 py-2 text-sm {{ $activeTab === 'health' ? 'bg-indigo-100 text-indigo-700' : 'text-gray-600 hover:bg-gray-100'}}">
                                Health
                            </a>
                        @endif
                    </nav>
                </div>

                <div class="p-6 text-gray-900">
                    @if($activeTab === 'posts')
                        @if($posts->isNotEmpty())
                            <div class="space-y-4">
                                @foreach($posts as $post)
                                    <x-post-card :post="$post" :myReactions="$myReactions ?? collect()" :mySaved="$mySaved ?? collect()" context="profile" />
                                @endforeach
                            </div>
                        @else
                            <p class="text-sm text-gray-500">No posts yet.</p>
                        @endif
                    @elseif($activeTab === 'gallery')
                        @if($gallery->isNotEmpty())
                            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                @foreach($gallery as $item)
                                    @php
                                        $url = method_exists($item, 'getUrl') ? $item->getUrl() : data_get($item, 'url');
                                        $label = data_get($item, 'name') ?: data_get($item, 'file_name', 'Gallery item');
                                     @endphp
                                    <div class="rounded-lg border border-gray-200 overflow-hidden">
                                        @if($url)
                                            <img src="{{ $url }}" alt="{{ $label }}" class="h-48 w-full object-cover">
                                        @else
                                            <div class="h-48 w-full bg-gray-100"></div>
                                        @endif
                                        <div class="p-3 text-sm text-gray-600">{{ $label }}</div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-sm text-gray-500">No gallery items yet.</p>
                        @endif
                    @elseif($activeTab === 'health' && $isOwner)
                        <div class="flex items-center justify-between">
                            <h3 class="text-base font-semibold text-gray-900">Recent health updates</h3>
                            <a href="{{ route('pets.health.index', $petSlug) }}"
                                class="text-sm text-indigo-600 hover:text-indigo-800">Open health log</a>
                        </div>

                        <div class="mt-4 rounded-lg border border-gray-200 bg-white p-4">
                            <h4 class="text-sm font-semibold text-gray-900">Weight history</h4>
                            <p class="mt-1 text-xs text-gray-500">Last 30 weight entries.</p>

                            @if(!empty($weightChartSvg))
                                <div class="mt-3" aria-label="Weight history chart">
                                    {!! $weightChartSvg !!}
                                </div>
                            @else
                                <p class="mt-3 text-sm text-gray-500">Add at least one weight entry to see a chart.</p>
                            @endif
                        </div>

                        @if($healthLogs->isNotEmpty())
                            <div class="mt-4 space-y-3">
                                @foreach($healthLogs as $log)
                                    <div class="rounded-lg border border-gray-200 p-4 text-sm">
                                        <div class="font-medium text-gray-900">
                                            @if(($log->log_type ?? null) === 'vaccine')
                                                Vaccination
                                            @else
                                                {{ ucfirst(str_replace('_', '', (string) ($log->log_type ?? 'entry'))) }}
                                            @endif
                                        </div>
                                        <div class="text-gray-600">
                                            @if(!is_null($log->weight_kg))
                                                {{ $log->weight_kg }} kg
                                            @elseif(!is_null($log->temperature_c))
                                                {{ $log->temperature_c }} °C
                                            @endif
                                            @if(!empty($log->notes))
                                                • {{ \Illuminate\Support\Str::limit((string) $log->notes, 120) }}
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="mt-2 text-sm text-gray-500">No health entries yet.</p>
                        @endif
                    @else
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <h3 class="text-sm font-semibold text-gray-900">Basic info</h3>
                                <dl class="mt-2 space-y-2 text-sm text-gray-600">
                                    <div>
                                        <dt class="inline font-medium text-gray-800">Species:</dt>
                                        {{ $pet->species ?? 'N/A'}}
                                    </div>
                                    <div>
                                        <dt class="inline font-medium text-gray-800">Breed:</dt> {{ $pet->breed ?? 'N/A'}}
                                    </div>
                                    <div>
                                        <dt class="inline font-medium text-gray-800">Sex:</dt>
                                        {{ $pet->sex ?? $pet->gender ?? 'N/A'}}
                                    </div>
                                    <div>
                                        <dt class="inline font-medium text-gray-800">Age:</dt> {{ $ageLabel ?? 'N/A'}}
                                    </div>
                                    <div>
                                        <dt class="inline font-medium text-gray-800">Birthdate:</dt>
                                        {{ $birthdateLabel ?? 'N/A'}}
                                    </div>
                                    <div>
                                        <dt class="inline font-medium text-gray-800">Weight:</dt>
                                        {{ $pet->weight_kg ? $pet->weight_kg . 'kg' : ($pet->weight ? $pet->weight . 'kg' : 'N/A') }}
                                    </div>
                                    <div>
                                        <dt class="inline font-medium text-gray-800">Color:</dt> {{ $pet->color ?? 'N/A'}}
                                    </div>
                                </dl>
                            </div>

                            <div>
                                <h3 class="text-sm font-semibold text-gray-900">Personality</h3>
                                @if(!empty($personalityTags))
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        @foreach($personalityTags as $tag)
                                            <span
                                                class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-700">{{ trim((string) $tag) }}</span>
                                        @endforeach
                                    </div>
                                @else
                                    <p class="mt-2 text-sm text-gray-500">No personality tags yet.</p>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @auth
        @if(!$isOwner)
            <script>
                (() => {
                    const button = document.getElementById('follow-toggle');

                    if (!button) {
                        return;
                    }

                    button.addEventListener('click', async () => {
                        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                        const following = button.dataset.following === '1';
                        const url = following ? button.dataset.unfollowUrl : button.dataset.followUrl;
                        const method = following ? 'DELETE' : 'POST';

                        try {
                            const response = await fetch(url, {
                                method,
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': csrf,
                                },
                            });

                            if (!response.ok) {
                                throw new Error('Request failed');
                            }

                            const data = await response.json();
                            button.dataset.following = data.followed ? '1' : '0';
                            button.textContent = data.followed ? 'Unfollow' : 'Follow';

                            const followersCount = document.getElementById('followers-count');
                            if (followersCount && typeof data.followers_count === 'number') {
                                followersCount.textContent = data.followers_count;
                            }
                        } catch (error) {
                            console.error(error);
                            alert('Could not update follow state.');
                        }
                    });
                })();
            </script>
        @endif
    @endauth
</x-app-layout>