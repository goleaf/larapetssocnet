<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header :title="$pet->name ?? __('pets.title')" description="Profile overview, gallery, and activity." icon="🐾">
            @if($isOwner)
                <x-slot name="action">
                    <div class="flex items-center gap-3">
                        <x-ui.button :href="route('pets.edit', $pet)" variant="ghost" size="sm">{{ __('pets.actions.edit_profile') }}</x-ui.button>
                        <x-ui.button :href="route('pets.health.index', $petSlug)" variant="ghost" size="sm">{{ __('pets.actions.health_logs') }}</x-ui.button>
                    </div>
                </x-slot>
            @endif
        </x-ui.page-header>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto space-y-6">
            @if (session('status'))
                <x-ui.alert type="success">{{ session('status') }}</x-ui.alert>
            @endif

            <x-ui.card padding="lg">
                <div class="flex flex-wrap items-start justify-between gap-6">
                    <div class="flex items-start gap-4">
                        <img src="{{ $avatarUrl }}" alt="{{ $pet->name }}" class="h-20 w-20 rounded-full object-cover border border-gray-200">

                        <div class="space-y-2">
                            <div class="text-sm text-gray-500">
                                {{ __('pets.species') }}: {{ $speciesLabel }}
                                @if($breedLabel)
                                    • {{ __('pets.breed') }}: {{ $breedLabel }}
                                @endif
                                @if($sexLabel)
                                    • {{ __('pets.sex') }}: {{ $sexLabel }}
                                @endif
                            </div>
                            @if($ageLabel)
                                <div class="text-sm text-gray-500">{{ __('pets.age') }}: {{ $ageLabel }}</div>
                            @endif
                            @if($birthdateLabel)
                                <div class="text-sm text-gray-500">{{ __('pets.birthdate') }}: {{ $birthdateLabel }}</div>
                            @endif
                            <div class="text-sm text-gray-600">{{ $pet->bio ?: __('pets.no_bio') }}</div>
                            <div class="flex flex-wrap gap-2">
                                @if(!empty($pet->is_adoptable))
                                    <x-ui.badge variant="success" size="sm">{{ __('pets.status.adoptable') }}</x-ui.badge>
                                @endif
                                @if($isOwner)
                                    @if(!empty($pet->is_public))
                                        <x-ui.badge variant="info" size="sm">{{ __('pets.status.public') }}</x-ui.badge>
                                    @else
                                        <x-ui.badge variant="warning" size="sm">{{ __('pets.status.private') }}</x-ui.badge>
                                    @endif
                                @elseif(!empty($pet->is_public))
                                    <x-ui.badge variant="info" size="sm">{{ __('pets.status.public') }}</x-ui.badge>
                                    <x-ui.badge variant="default" size="sm">{{ __('pets.status.visible_profile') }}</x-ui.badge>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="space-y-3 text-right">
                        <div class="flex items-center justify-end gap-2">
                            <x-user-avatar :user="$pet->user" size="sm" />
                            <span class="text-sm text-gray-600">{{ $pet->user?->name }}</span>
                        </div>

                        <p class="text-xs text-gray-500">{{ $postsCount }} {{ __('pets.tabs.posts') }}</p>

                        @can('viewFollowers', $pet)
                            <a href="{{ route('pets.followers.index', ['pet' => $petSlug]) }}" class="text-xs text-gray-500 hover:underline">
                                {{ $followersCount }} {{ __('pets.followers') }}
                            </a>
                        @else
                            <p class="text-xs text-gray-500">{{ $followersCount }} {{ __('pets.followers') }}</p>
                        @endcan

                        @if(!$isOwner)
                            <x-follow-button :target="$pet" size="sm" />
                        @endif
                    </div>
                </div>
            </x-ui.card>

            @php
                $petTabs = [
                    ['label' => __('pets.tabs.posts'), 'value' => 'posts'],
                    ['label' => __('pets.tabs.gallery'), 'value' => 'gallery'],
                    ['label' => __('pets.tabs.about'), 'value' => 'about'],
                ];
                if ($isOwner) {
                    $petTabs[] = ['label' => __('pets.tabs.health'), 'value' => 'health'];
                }
            @endphp

            <x-ui.card padding="none">
                <div class="px-6 pt-4">
                    <x-ui.tabs :tabs="$petTabs" :active="$activeTab" paramName="tab"/>
                </div>

                <div class="p-6 text-bark">
                    @if($activeTab === 'posts')
                        @if($posts->isEmpty())
                            <x-ui.empty-state title="{{ __('pets.no_posts') }}" description=""/>
                        @else
                            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                @foreach($posts as $post)
                                    <x-ui.card padding="sm">
                                        <p class="text-sm text-fur line-clamp-4">{{ \Illuminate\Support\Str::limit(strip_tags((string) ($post->body_html ?: $post->body)), 220) }}</p>
                                        <p class="mt-3 text-xs text-fur">{{ optional($post->created_at)->diffForHumans() }}</p>
                                    </x-ui.card>
                                @endforeach
                            </div>

                            <div class="mt-6">
                                {{ $posts->links() }}
                            </div>
                        @endif
                    @elseif($activeTab === 'gallery')
                        @if($gallery->isEmpty())
                            <x-ui.empty-state title="{{ __('pets.no_gallery') }}" description=""/>
                        @else
                            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                @foreach($gallery as $item)
                                    <x-ui.card padding="none" class="overflow-hidden">
                                        @if(!empty($item['url']))
                                            <img src="{{ $item['url'] }}" alt="{{ $item['alt'] }}" class="h-48 w-full object-cover">
                                        @else
                                            <div class="h-48 w-full bg-gray-100"></div>
                                        @endif
                                        <div class="p-3 text-sm text-gray-600">
                                            <div class="font-medium">{{ $item['label'] }}</div>
                                            @if($item['caption'] !== '')
                                                <div class="mt-1 text-xs text-gray-500">{{ $item['caption'] }}</div>
                                            @endif
                                        </div>
                                    </x-ui.card>
                                @endforeach
                            </div>
                        @endif
                    @elseif($activeTab === 'health' && $isOwner)
                        <div class="flex items-center justify-between">
                            <h3 class="text-base font-semibold text-bark">{{ __('pets.weight_history') }}</h3>
                            <x-ui.button :href="route('pets.health.index', $petSlug)" variant="ghost" size="sm">{{ __('pets.view_health_log') }}</x-ui.button>
                        </div>

                        <x-ui.card padding="md" class="mt-4">
                            <p class="text-xs text-fur">{{ __('pets.weight_history_hint') }}</p>

                            @if(!empty($weightChartSvg))
                                <div class="mt-3" aria-label="{{ __('pets.weight_history_chart_label') }}">
                                    {!! $weightChartSvg !!}
                                </div>
                            @else
                                <p class="mt-3 text-sm text-fur">{{ __('pets.weight_history_empty') }}</p>
                            @endif
                        </x-ui.card>

                        @if($healthLogs->isNotEmpty())
                            <div class="mt-4 space-y-3">
                                @foreach($healthLogs as $log)
                                    <x-ui.card padding="sm">
                                        <div class="font-medium text-bark">{{ ucfirst(str_replace('_', ' ', (string) ($log->log_type ?? 'entry'))) }}</div>
                                        <div class="text-fur">
                                            @if(!is_null($log->weight_kg))
                                                {{ $log->weight_kg }} kg
                                            @elseif(!is_null($log->temperature_c))
                                                {{ $log->temperature_c }} °C
                                            @endif
                                            @if(!empty($log->notes))
                                                • {{ \Illuminate\Support\Str::limit((string) $log->notes, 120) }}
                                            @endif
                                        </div>
                                    </x-ui.card>
                                @endforeach
                            </div>
                        @else
                            <p class="mt-2 text-sm text-fur">{{ __('pets.no_health_logs') }}</p>
                        @endif
                    @else
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <h3 class="text-sm font-semibold text-bark">{{ __('pets.bio') }}</h3>
                                <p class="mt-2 text-sm text-fur">{{ $pet->bio ?: __('pets.no_bio') }}</p>
                            </div>

                            <div>
                                <h3 class="text-sm font-semibold text-bark">{{ __('pets.personality') }}</h3>
                                @if(!empty($personalityTags))
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        @foreach($personalityTags as $tag)
                                            <x-ui.badge variant="default" size="sm">{{ \Illuminate\Support\Str::headline(trim((string) $tag)) }}</x-ui.badge>
                                        @endforeach
                                    </div>
                                @else
                                    <p class="mt-2 text-sm text-fur">{{ __('pets.no_personality_tags') }}</p>
                                @endif
                            </div>
                        </div>

                        @if(!empty($pet->is_adoptable) || ($pet->adoption_status ?? 'not_listed') !== 'not_listed')
                            <x-ui.card padding="md" class="mt-6 border-emerald-100 bg-emerald-50/60">
                                <div class="flex flex-wrap items-center gap-2">
                                    @if(!empty($pet->is_adoptable))
                                        <x-ui.badge variant="success" size="sm">{{ __('pets.status.adoptable') }}</x-ui.badge>
                                        <span class="text-sm text-emerald-700">{{ __('pets.adoption.badge_note') }}</span>
                                    @endif

                                    @if(($pet->adoption_status ?? 'not_listed') === 'available')
                                        <x-ui.badge variant="success" size="sm">{{ __('pets.adoption.listed') }}</x-ui.badge>
                                    @elseif(($pet->adoption_status ?? 'not_listed') === 'pending')
                                        <x-ui.badge variant="warning" size="sm">{{ __('pets.adoption.pending') }}</x-ui.badge>
                                    @elseif(($pet->adoption_status ?? 'not_listed') === 'adopted')
                                        <x-ui.badge variant="default" size="sm">{{ __('pets.adoption.adopted') }}</x-ui.badge>
                                    @endif
                                </div>

                                @if(($pet->adoption_status ?? 'not_listed') === 'available')
                                    <div class="mt-3 grid gap-2 text-sm text-emerald-800 sm:grid-cols-2">
                                        <div>
                                            <div class="text-xs uppercase tracking-wide text-emerald-500">{{ __('pets.adoption.fee') }}</div>
                                            <div class="font-semibold">
                                                {{ filled($pet->adoption_fee) ? '$'.number_format((int) $pet->adoption_fee) : __('pets.adoption.fee_free') }}
                                            </div>
                                        </div>

                                        @if(!empty($pet->adoption_contact))
                                            <div>
                                                <div class="text-xs uppercase tracking-wide text-emerald-500">{{ __('pets.adoption.contact') }}</div>
                                                <div class="font-semibold">{{ $pet->adoption_contact }}</div>
                                            </div>
                                        @endif
                                    </div>

                                    @if(!empty($pet->adoption_notes))
                                        <p class="mt-3 text-sm text-emerald-700">{{ $pet->adoption_notes }}</p>
                                    @endif
                                @endif
                            </x-ui.card>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
