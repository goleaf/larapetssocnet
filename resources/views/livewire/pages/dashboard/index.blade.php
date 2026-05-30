<x-app-layout>
 <x-slot name="header">
 <x-ui.page-header :title="__('Dashboard')" description="Your daily starting point for posts, pets, groups, and messages." icon="📊">
 <x-slot:action>
 <div class="flex flex-wrap items-center gap-2">
 <x-ui.button href="{{ route('feed.index') }}" variant="ghost" size="sm">{{ __('Open feed') }}</x-ui.button>
 <x-ui.button href="{{ $this->profileHref }}" variant="primary" size="sm">{{ __('View profile') }}</x-ui.button>
 </div>
 </x-slot:action>
 </x-ui.page-header>
 </x-slot>

 <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_20rem]">
 <div class="space-y-5">
 <x-ui.card padding="lg" class="overflow-hidden">
 <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_18rem] lg:items-stretch">
 <div class="space-y-5">
 <div>
 <p class="shell-kicker">{{ __('Welcome back') }}</p>
 <h2 class="mt-2 text-3xl font-bold font-display text-bark sm:text-4xl">
 {{ __('Hi :name, keep your pet world moving.', ['name' => $this->firstName]) }}
 </h2>
 <p class="mt-3 max-w-2xl text-base leading-7 text-fur">
 {{ __('Use the dashboard for the next action: publish something, update a pet profile, join a group, or check conversations.') }}
 </p>
 </div>

 <div class="flex flex-wrap gap-3">
 <x-ui.button href="{{ $this->createPostHref }}" variant="primary">
 {{ __('Create post') }}
 </x-ui.button>
 <x-ui.button href="{{ $this->explorePetsHref }}" variant="outline">
 {{ __('Explore pets') }}
 </x-ui.button>
 </div>
 </div>

 <div class="ui-subtle-card p-4">
 <div class="flex items-center gap-3">
 <x-ui.avatar :name="$this->viewer?->name ?? __('User')" :src="$this->viewer?->avatar_url" :user="$this->viewer" size="lg"/>
 <div class="min-w-0">
 <p class="truncate text-sm font-semibold text-bark">{{ $this->viewer?->name ?? __('User') }}</p>
 <p class="truncate text-xs text-fur">{{ $this->viewer?->username ? '@'.$this->viewer->username : $this->viewer?->email }}</p>
 </div>
 </div>

 <dl class="mt-5 grid grid-cols-2 gap-2 text-sm">
 <div class="ui-list-item px-3 py-3">
 <dt class="text-xs font-semibold uppercase tracking-[0.08em] text-fur">{{ __('Status') }}</dt>
 <dd class="mt-1 font-semibold text-bark">{{ __('Active') }}</dd>
 </div>
 <div class="ui-list-item px-3 py-3">
 <dt class="text-xs font-semibold uppercase tracking-[0.08em] text-fur">{{ __('Privacy') }}</dt>
 <dd class="mt-1 font-semibold text-bark">{{ $this->viewer?->is_private ? __('Private') : __('Public') }}</dd>
 </div>
 </dl>
 </div>
 </div>
 </x-ui.card>

 <section aria-labelledby="dashboard-quick-actions">
 <div class="mb-3 flex items-center justify-between gap-3">
 <div>
 <p class="shell-kicker">{{ __('Shortcuts') }}</p>
 <h2 id="dashboard-quick-actions" class="mt-1 text-xl font-bold font-display text-bark">{{ __('Quick actions') }}</h2>
 </div>
 </div>

 <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
 @foreach ($this->quickActions as $action)
 <a
 href="{{ $action['href'] }}"
 wire:key="dashboard-action-{{ $action['key'] }}"
 class="shell-card ui-card-interactive group flex min-h-32 flex-col justify-between p-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw"
 >
 <span class="inline-flex h-10 w-10 items-center justify-center rounded-[var(--radius-soft)] text-lg {{ $action['tone'] }}" aria-hidden="true">{{ $action['icon'] }}</span>
 <span>
 <span class="block text-base font-semibold text-bark transition-colors group-hover:text-paw-dark">{{ $action['label'] }}</span>
 <span class="mt-1 block text-sm leading-5 text-fur">{{ $action['description'] }}</span>
 </span>
 </a>
 @endforeach
 </div>
 </section>
 </div>

 <aside class="space-y-5 xl:sticky xl:top-24 xl:self-start">
 <x-ui.card padding="lg">
 <x-slot name="header">
 <x-ui.card-header title="{{ __('Today') }}" subtitle="{{ __('Small checks that keep the account useful.') }}" />
 </x-slot>

 <ul class="space-y-3">
 <li class="flex gap-3">
 <span class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-[var(--radius-soft)] bg-leaf-light text-xs font-bold text-leaf">1</span>
 <div>
 <p class="text-sm font-semibold text-bark">{{ __('Review your profile') }}</p>
 <p class="text-xs leading-5 text-fur">{{ __('Keep your username, bio, and privacy settings current.') }}</p>
 </div>
 </li>
 <li class="flex gap-3">
 <span class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-[var(--radius-soft)] bg-paw-light text-xs font-bold text-paw-dark">2</span>
 <div>
 <p class="text-sm font-semibold text-bark">{{ __('Check pet details') }}</p>
 <p class="text-xs leading-5 text-fur">{{ __('Refresh care notes, adoption status, or gallery photos.') }}</p>
 </div>
 </li>
 <li class="flex gap-3">
 <span class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-[var(--radius-soft)] bg-sky-light text-xs font-bold text-sky">3</span>
 <div>
 <p class="text-sm font-semibold text-bark">{{ __('Respond to activity') }}</p>
 <p class="text-xs leading-5 text-fur">{{ __('Open messages, notifications, and group updates.') }}</p>
 </div>
 </li>
 </ul>
 </x-ui.card>

 <x-ui.card padding="lg">
 <x-slot name="header">
 <x-ui.card-header title="{{ __('Community paths') }}" subtitle="{{ __('Jump into discovery without hunting through menus.') }}" />
 </x-slot>

 <div class="space-y-2">
 <x-ui.user-row name="{{ __('Groups') }}" subtitle="{{ __('Communities and discussions') }}" href="{{ route('groups.index') }}">
 <x-slot name="avatar"><span class="text-lg" aria-hidden="true">👥</span></x-slot>
 </x-ui.user-row>
 <x-ui.user-row name="{{ __('Events') }}" subtitle="{{ __('Walks, meetups, and activities') }}" href="{{ route('events.index') }}">
 <x-slot name="avatar"><span class="text-lg" aria-hidden="true">📅</span></x-slot>
 </x-ui.user-row>
 <x-ui.user-row name="{{ __('Adoption') }}" subtitle="{{ __('Pets looking for homes') }}" href="{{ route('pets.adopt') }}">
 <x-slot name="avatar"><span class="text-lg" aria-hidden="true">🏡</span></x-slot>
 </x-ui.user-row>
 </div>
 </x-ui.card>
 </aside>
 </div>
</x-app-layout>
