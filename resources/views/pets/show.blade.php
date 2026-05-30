<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header :title="$pet->name ?? __('pets.title')" description="Profile overview, gallery, and activity." icon="🐾">
            @if($isOwner)
                <x-slot name="action">
                    <div class="flex flex-wrap items-center gap-2">
                        <x-ui.button :href="route('pets.edit', $pet)" variant="ghost" size="sm">{{ __('pets.actions.edit_profile') }}</x-ui.button>
                        <x-ui.button :href="route('pets.health.index', $petSlug)" variant="ghost" size="sm">{{ __('pets.actions.health_logs') }}</x-ui.button>
                    </div>
                </x-slot>
            @endif
        </x-ui.page-header>
    </x-slot>

    <x-ui.page-stack data-ui="pet-profile-stack">
        @if (session('status'))
            <x-ui.alert type="success">{{ session('status') }}</x-ui.alert>
        @endif

        <x-ui.card padding="lg" data-ui="pet-profile-summary">
            <div class="grid min-w-0 gap-6 lg:grid-cols-[minmax(0,1fr)_18rem] lg:items-start">
                <div class="flex min-w-0 flex-col gap-5 sm:flex-row">
                    <div class="ui-media-frame h-24 w-24 shrink-0">
                        <img src="{{ $avatarUrl }}" alt="{{ $pet->name }}" class="h-full w-full object-cover">
                    </div>

                    <div class="min-w-0 space-y-4">
                        <div class="space-y-2">
                            <p class="shell-kicker">{{ __('pets.species') }}: {{ $speciesLabel }}</p>
                            <div class="flex flex-wrap items-center gap-2">
                                @if($breedLabel)
                                    <x-ui.badge variant="default" size="sm">{{ __('pets.breed') }}: {{ $breedLabel }}</x-ui.badge>
                                @endif
                                @if($sexLabel)
                                    <x-ui.badge variant="default" size="sm">{{ __('pets.sex') }}: {{ $sexLabel }}</x-ui.badge>
                                @endif
                                @if($ageLabel)
                                    <x-ui.badge variant="default" size="sm">{{ __('pets.age') }}: {{ $ageLabel }}</x-ui.badge>
                                @endif
                                @if($birthdateLabel)
                                    <x-ui.badge variant="default" size="sm">{{ __('pets.birthdate') }}: {{ $birthdateLabel }}</x-ui.badge>
                                @endif
                            </div>
                        </div>

                        <p class="max-w-4xl text-sm leading-6 shell-text-muted">{{ $pet->bio ?: __('pets.no_bio') }}</p>

                        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4" data-ui="pet-profile-identity-facts">
                            @foreach($identityFacts as $fact)
                                <div class="ui-list-item p-3" data-ui="pet-profile-identity-fact">
                                    <p class="text-xs font-semibold uppercase tracking-[0.08em] shell-text-muted">{{ $fact['label'] }}</p>
                                    <p class="mt-1 text-sm font-semibold text-bark" @if($fact['label'] === 'Life stage') data-ui="pet-life-stage" @endif>{{ $fact['value'] }}</p>
                                </div>
                            @endforeach
                        </div>

                        @if(!empty($personalityTags))
                            <div class="flex flex-wrap gap-2" data-ui="pet-profile-personality-strip">
                                @foreach(array_slice($personalityTags, 0, 8) as $tag)
                                    <span class="ui-token">{{ \Illuminate\Support\Str::headline(trim((string) $tag)) }}</span>
                                @endforeach
                            </div>
                        @endif

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

                <aside class="min-w-0 space-y-3" aria-label="{{ __('pets.title') }}">
                    <div class="ui-list-item flex items-center gap-3 p-3">
                        <x-user-avatar :user="$pet->user" size="sm" />
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-bark">{{ $pet->user?->name }}</p>
                            <p class="text-xs shell-text-muted">{{ __('pets.owner') }}</p>
                        </div>
                    </div>

                    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-1">
                        <div class="ui-list-item p-3">
                            <p class="text-xs font-semibold uppercase tracking-[0.08em] shell-text-muted">{{ __('pets.tabs.posts') }}</p>
                            <p class="mt-1 text-lg font-bold font-display text-bark">{{ number_format($postsCount) }}</p>
                        </div>

                        <div class="ui-list-item p-3">
                            <p class="text-xs font-semibold uppercase tracking-[0.08em] shell-text-muted">{{ __('pets.followers') }}</p>
                            @can('viewFollowers', $pet)
                                <a href="{{ route('pets.followers.index', ['pet' => $petSlug]) }}" class="mt-1 inline-flex text-lg font-bold font-display text-bark hover:text-paw">
                                    {{ number_format($followersCount) }}
                                </a>
                            @else
                                <p class="mt-1 text-lg font-bold font-display text-bark">{{ number_format($followersCount) }}</p>
                            @endcan
                        </div>
                    </div>

                    @if(!empty($careSnapshot))
                        <div class="ui-panel p-3" data-ui="pet-profile-care-snapshot">
                            <p class="shell-kicker">Care notes</p>
                            <div class="mt-3 space-y-2">
                                @foreach($careSnapshot as $careItem)
                                    <div class="ui-list-item p-3" data-ui="pet-profile-care-item">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <p class="text-xs font-semibold uppercase tracking-[0.08em] shell-text-muted">{{ $careItem['label'] }}</p>
                                                <p class="mt-1 truncate text-sm font-semibold text-bark">{{ $careItem['value'] }}</p>
                                            </div>
                                            @if($careItem['meta'])
                                                <p class="shrink-0 text-xs shell-text-muted">{{ $careItem['meta'] }}</p>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if(!$isOwner)
                        <div class="pt-1">
                            <x-follow-button :target="$pet" size="sm" />
                        </div>
                    @endif

                    <div
                        data-ui="pet-profile-qr"
                        x-data="{ copied: false }"
                        class="ui-panel p-3">
                        <div class="flex items-center gap-3">
                            <img
                                data-ui="pet-profile-qr-code"
                                src="{{ $qrCodeUrl }}"
                                alt="QR code for {{ $pet->name }}"
                                class="h-24 w-24 rounded-[var(--radius-soft)] border border-whisker/40 bg-warm-white p-1">
                            <div class="min-w-0 space-y-2">
                                <p class="shell-kicker">Share profile</p>
                                <div class="flex flex-wrap gap-2">
                                    <x-ui.button :href="$qrDownloadUrl" variant="outline" size="xs" data-ui="pet-profile-qr-download">Download</x-ui.button>
                                    <button
                                        type="button"
                                        class="btn-base btn-ghost h-[var(--control-height-sm)] px-2.5 text-xs"
                                        data-ui="pet-profile-copy-link"
                                        @click="if (navigator.clipboard?.writeText) { navigator.clipboard.writeText(@js(route('pets.show', $pet))).then(() => { copied = true; setTimeout(() => copied = false, 1500) }) }">
                                        <span x-text="copied ? 'Copied' : 'Copy link'">Copy link</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </aside>
            </div>

            <div class="mt-6 grid gap-4 lg:grid-cols-[minmax(0,1fr)_18rem]" data-ui="pet-profile-identity-story">
                <section class="ui-panel p-4" data-ui="pet-profile-life-story-preview">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="shell-kicker">Life story</p>
                            <h2 class="mt-1 font-display text-xl font-semibold text-bark">{{ $pet->name }}'s timeline</h2>
                        </div>
                        <x-ui.button :href="route('pets.show', ['pet' => $pet, 'tab' => 'milestones'])" variant="ghost" size="sm">View milestones</x-ui.button>
                    </div>

                    @if($featuredMilestones->isEmpty())
                        <p class="mt-3 text-sm leading-6 shell-text-muted">This story is just beginning. Milestones will turn care moments, firsts, and favorite memories into a living diary.</p>
                    @else
                        <ol class="mt-4 grid gap-3 sm:grid-cols-3">
                            @foreach($featuredMilestones as $featuredMilestone)
                                <li class="ui-list-item p-3" data-ui="pet-profile-featured-milestone">
                                    <p class="text-xs font-semibold uppercase tracking-[0.08em] shell-text-muted">{{ optional($featuredMilestone->occurred_on)->toFormattedDateString() }}</p>
                                    <p class="mt-1 text-sm font-semibold text-bark">{{ $featuredMilestone->title }}</p>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </section>

                <section class="ui-panel p-4" data-ui="pet-profile-stewardship">
                    <p class="shell-kicker">Stewarded by</p>
                    <div class="mt-3 flex items-center gap-3">
                        <x-user-avatar :user="$pet->user" size="sm" />
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-bark">{{ $pet->user?->name }}</p>
                            <p class="text-xs shell-text-muted">Primary caretaker</p>
                        </div>
                    </div>
                    <p class="mt-3 text-xs leading-5 shell-text-muted">PetSocial profiles are maintained by people who know the animal, while the pet keeps their own followers, posts, photos, and history.</p>
                </section>
            </div>
        </x-ui.card>

        @php
            $petTabs = [
                ['label' => __('pets.tabs.posts'), 'value' => 'posts'],
                ['label' => __('pets.tabs.gallery'), 'value' => 'gallery'],
                ['label' => 'Milestones', 'value' => 'milestones'],
            ];
            if (($pet->adoption_status ?? 'not_listed') !== 'not_listed' || !empty($pet->is_adoptable)) {
                $petTabs[] = ['label' => 'Adopt', 'value' => 'adopt'];
            }
            $petTabs[] = ['label' => __('pets.tabs.about'), 'value' => 'about'];
            if ($isOwner) {
                $petTabs[] = ['label' => __('pets.tabs.health'), 'value' => 'health'];
            }
            $petTabValues = collect($petTabs)->pluck('value')->values()->all();
        @endphp

        <x-ui.card
            id="pet-profile-tabs"
            padding="none"
            data-ui="pet-profile-tabs"
            class="sticky top-20 z-30 scroll-mt-24 border-whisker/50 bg-warm-white/85 backdrop-blur-md!"
            x-data="profileTabs({ activeTab: @js($activeTab), tabs: @js($petTabValues), scrollTarget: 'pet-profile-tabs' })"
            @click="selectFromClick($event)">
            <div class="px-4 pt-4 sm:px-6">
                <x-ui.tabs :tabs="$petTabs" :active="$activeTab" paramName="tab" :animated-indicator="true" class="mb-0"/>
            </div>
        </x-ui.card>

        <div class="min-w-0" data-ui="pet-profile-tab-content">
            @if(!$canViewTimelineContent)
                <x-ui.card padding="lg" data-ui="pet-profile-content-locked">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="shell-kicker">Profile preview</p>
                            <h3 class="mt-1 font-display text-lg font-semibold text-bark">Follow {{ $pet->name }} to see more</h3>
                            <p class="mt-2 max-w-2xl text-sm leading-6 shell-text-muted">This pet's posts, photos, and milestone timeline are visible to followers, while the identity header remains available as a preview.</p>
                        </div>
                        @if(!$isOwner)
                            <x-follow-button :target="$pet" size="sm" />
                        @endif
                    </div>
                </x-ui.card>
            @elseif($activeTab === 'posts')
                @if($posts->isEmpty())
                    <x-ui.card padding="lg" data-ui="pet-profile-empty-posts">
                        <x-ui.empty-state title="{{ __('pets.no_posts') }}" description=""/>
                    </x-ui.card>
                @else
                    <div class="grid min-w-0 gap-4 sm:grid-cols-2 xl:grid-cols-3" data-ui="pet-profile-posts-grid">
                        @foreach($posts as $post)
                            <x-ui.card padding="sm" data-ui="pet-profile-post-card">
                                <p class="line-clamp-4 text-sm leading-6 shell-text-muted">{{ \Illuminate\Support\Str::limit(strip_tags((string) ($post->body_html ?: $post->body)), 220) }}</p>
                                <p class="mt-3 text-xs shell-text-muted">{{ optional($post->created_at)->diffForHumans() }}</p>
                            </x-ui.card>
                        @endforeach
                    </div>

                    @if($posts->hasPages())
                        <x-ui.card padding="sm" class="mt-5" data-ui="pet-profile-pagination">
                            {{ $posts->links() }}
                        </x-ui.card>
                    @endif
                @endif
            @elseif($activeTab === 'gallery')
                @if($gallery->isEmpty())
                    <x-ui.card padding="lg" data-ui="pet-profile-empty-gallery">
                        <x-ui.empty-state title="{{ __('pets.no_gallery') }}" description=""/>
                    </x-ui.card>
                @else
                    <div class="grid min-w-0 gap-4 sm:grid-cols-2 xl:grid-cols-3" data-ui="pet-profile-gallery-grid">
                        @foreach($gallery as $item)
                            <x-ui.card padding="none" class="overflow-hidden" data-ui="pet-profile-gallery-card">
                                @if(!empty($item['url']))
                                    <div class="ui-media-frame rounded-none border-0">
                                        <img src="{{ $item['url'] }}" alt="{{ $item['alt'] }}" class="h-52 w-full object-cover">
                                    </div>
                                @else
                                    <div class="h-52 w-full bg-[color:var(--surface-muted)]"></div>
                                @endif
                                <div class="p-4">
                                    <div class="text-sm font-semibold text-bark">{{ $item['label'] }}</div>
                                    @if($item['caption'] !== '')
                                        <div class="mt-1 text-xs shell-text-muted">{{ $item['caption'] }}</div>
                                    @endif
                                </div>
                            </x-ui.card>
                        @endforeach
                    </div>
                @endif
            @elseif($activeTab === 'milestones')
                <x-ui.card padding="lg" data-ui="pet-profile-milestones">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="shell-kicker">Milestones</p>
                            <h3 class="mt-1 font-display text-lg font-semibold text-bark">Timeline</h3>
                        </div>
                    </div>

                    @can('manageMilestones', $pet)
                        <form method="POST" action="{{ route('pets.milestones.store', $pet) }}" class="ui-panel mt-4 grid gap-4 p-4" data-ui="pet-milestone-add-form">
                            @csrf
                            <div class="grid gap-4 sm:grid-cols-2">
                                <x-ui.input id="milestone_title" name="title" label="Title" required />
                                <x-ui.input id="milestone_occurred_on" name="occurred_on" type="date" label="Date" :value="now()->toDateString()" required />
                            </div>
                            <x-ui.textarea id="milestone_body" name="body" rows="3" label="Details" />
                            <x-ui.checkbox name="share_as_post" label="Share as a pet post" />
                            <div>
                                <x-ui.button type="submit" variant="primary" size="sm">Add milestone</x-ui.button>
                            </div>
                        </form>
                    @endcan

                    @if($milestones->isEmpty())
                        <x-ui.empty-state title="No milestones yet" description="" />
                    @else
                        <ol class="mt-5 space-y-4 border-l border-whisker/50 pl-5" data-ui="pet-milestone-timeline">
                            @foreach($milestones as $milestone)
                                <li class="relative" data-ui="pet-milestone-item">
                                    <span class="absolute -left-[1.72rem] top-1.5 h-3 w-3 rounded-full border-2 border-warm-white bg-paw"></span>
                                    <div class="ui-list-item p-4">
                                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                            <div class="min-w-0">
                                                <p class="text-xs font-semibold uppercase text-fur">{{ optional($milestone->occurred_on)->toFormattedDateString() }}</p>
                                                <h4 class="mt-1 font-display text-base font-semibold text-bark">{{ $milestone->title }}</h4>
                                            </div>
                                            @if($milestone->post_id)
                                                <x-ui.badge variant="success" size="sm">Shared</x-ui.badge>
                                            @endif
                                        </div>

                                        @if($milestone->body_html)
                                            <div class="mt-2 text-sm leading-6 shell-text-muted">{!! $milestone->body_html !!}</div>
                                        @endif

                                        @can('manageMilestones', $pet)
                                            <details class="mt-3" data-ui="pet-milestone-edit">
                                                <summary class="cursor-pointer text-sm font-semibold text-paw">Edit</summary>
                                                <form method="POST" action="{{ route('pets.milestones.update', ['pet' => $pet, 'milestone' => $milestone]) }}" class="mt-3 grid gap-3">
                                                    @csrf
                                                    @method('PATCH')
                                                    <x-ui.input id="milestone_{{ $milestone->getKey() }}_title" name="title" label="Title" :value="$milestone->title" required />
                                                    <x-ui.input id="milestone_{{ $milestone->getKey() }}_occurred_on" name="occurred_on" type="date" label="Date" :value="$milestone->occurred_on?->toDateString()" required />
                                                    <x-ui.textarea id="milestone_{{ $milestone->getKey() }}_body" name="body" rows="3" label="Details" :value="$milestone->body" />
                                                    <div class="flex flex-wrap gap-2">
                                                        <x-ui.button type="submit" variant="primary" size="sm">Save</x-ui.button>
                                                    </div>
                                                </form>
                                            </details>
                                        @endcan
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </x-ui.card>
            @elseif($activeTab === 'adopt')
                <x-ui.card padding="lg" data-ui="pet-profile-adopt">
                    <div class="flex flex-wrap items-center gap-2">
                        @if(!empty($pet->is_adoptable))
                            <x-ui.badge variant="success" size="sm">{{ __('pets.status.adoptable') }}</x-ui.badge>
                            <span class="text-sm shell-text-muted">{{ __('pets.adoption.badge_note') }}</span>
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
                        <div class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
                            <div>
                                <div class="shell-kicker">{{ __('pets.adoption.fee') }}</div>
                                <div class="mt-1 font-semibold text-bark">
                                    {{ filled($pet->adoption_fee) ? '$'.number_format((int) $pet->adoption_fee) : __('pets.adoption.fee_free') }}
                                </div>
                            </div>

                            @if(!empty($pet->adoption_contact))
                                <div>
                                    <div class="shell-kicker">{{ __('pets.adoption.contact') }}</div>
                                    <div class="mt-1 font-semibold text-bark">{{ $pet->adoption_contact }}</div>
                                </div>
                            @endif
                        </div>

                        @if(!empty($pet->adoption_notes))
                            <p class="mt-3 text-sm shell-text-muted">{{ $pet->adoption_notes }}</p>
                        @endif
                    @endif
                </x-ui.card>
            @elseif($activeTab === 'health' && $isOwner)
                <x-ui.card padding="lg" data-ui="pet-profile-health">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="shell-kicker">{{ __('pets.tabs.health') }}</p>
                            <h3 class="mt-1 text-lg font-semibold font-display text-bark">{{ __('pets.weight_history') }}</h3>
                        </div>
                        <x-ui.button :href="route('pets.health.index', $petSlug)" variant="ghost" size="sm">{{ __('pets.view_health_log') }}</x-ui.button>
                    </div>

                    <div class="ui-panel mt-4 p-4">
                        <p class="text-xs shell-text-muted">{{ __('pets.weight_history_hint') }}</p>

                        @if(!empty($weightChartSvg))
                            <div class="mt-3" aria-label="{{ __('pets.weight_history_chart_label') }}">
                                {!! $weightChartSvg !!}
                            </div>
                        @else
                            <p class="mt-3 text-sm shell-text-muted">{{ __('pets.weight_history_empty') }}</p>
                        @endif
                    </div>

                    @if($healthLogs->isNotEmpty())
                        <div class="mt-4 grid gap-3">
                            @foreach($healthLogs as $log)
                                <div class="ui-list-item p-4">
                                    <div class="font-semibold text-bark">{{ ucfirst(str_replace('_', ' ', (string) ($log->log_type ?? 'entry'))) }}</div>
                                    <div class="mt-1 text-sm shell-text-muted">
                                        @if(!is_null($log->weight_kg))
                                            {{ $log->weight_kg }} kg
                                        @elseif(!is_null($log->temperature_c))
                                            {{ $log->temperature_c }} °C
                                        @endif
                                        @if(!empty($log->notes))
                                            <span class="mx-1">•</span>{{ \Illuminate\Support\Str::limit((string) $log->notes, 120) }}
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="mt-4 text-sm shell-text-muted">{{ __('pets.no_health_logs') }}</p>
                    @endif
                </x-ui.card>
            @else
                <x-ui.card padding="lg" data-ui="pet-profile-about">
                    <div class="grid gap-6 lg:grid-cols-2">
                        <section class="min-w-0">
                            <p class="shell-kicker">{{ __('pets.bio') }}</p>
                            <p class="mt-2 text-sm leading-6 shell-text-muted">{{ $pet->bio ?: __('pets.no_bio') }}</p>
                        </section>

                        <section class="min-w-0">
                            <p class="shell-kicker">{{ __('pets.personality') }}</p>
                            @if(!empty($personalityTags))
                                <div class="mt-3 flex flex-wrap gap-2">
                                    @foreach($personalityTags as $tag)
                                        <x-ui.badge variant="default" size="sm">{{ \Illuminate\Support\Str::headline(trim((string) $tag)) }}</x-ui.badge>
                                    @endforeach
                                </div>
                            @else
                                <p class="mt-2 text-sm shell-text-muted">{{ __('pets.no_personality_tags') }}</p>
                            @endif
                        </section>
                    </div>

                </x-ui.card>
            @endif
        </div>
    </x-ui.page-stack>
</x-app-layout>
