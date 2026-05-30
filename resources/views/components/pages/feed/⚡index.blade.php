<?php

use App\Models\Identity\User;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;

new
#[Layout('layouts.livewire-pass-through')]
class extends Component
{
    public string $source = '';

    public string $type = '';

    public bool $showWelcomeBanner = false;

    public bool $showOnboardingPetReminder = false;

    public User $user;

    public function mount(): void
    {
        $this->source = $this->sanitizeSource(request()->query('source'));
        $this->type = $this->sanitizeType(request()->query('type'));

        $this->user = $this->viewer();
        $onboardingCompletedAt = $this->user->onboarding_completed_at;

        $this->showWelcomeBanner = $onboardingCompletedAt !== null
            && Carbon::parse((string) $onboardingCompletedAt)->greaterThanOrEqualTo(now()->subDay())
            && ! request()->session()->has('onboarding_welcome_banner_dismissed');

        $this->showOnboardingPetReminder = (bool) $this->user->onboarding_pet_reminder_pending
            && $this->user->onboarding_pet_reminder_shown_at === null;

        if ($this->showOnboardingPetReminder) {
            $this->user->forceFill([
                'onboarding_pet_reminder_shown_at' => now(),
            ])->saveQuietly();
        }
    }

    private function viewer(): User
    {
        $viewer = auth()->user();

        abort_unless($viewer instanceof User, 403);

        return $viewer;
    }

    private function sanitizeSource(mixed $source): string
    {
        return is_string($source) && in_array($source, ['people', 'pets'], true) ? $source : '';
    }

    private function sanitizeType(mixed $type): string
    {
        return is_string($type) && in_array($type, ['text', 'photo', 'video'], true) ? $type : '';
    }
};
?>

@section('title', __('feed.page_title'))

<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header :title="__('feed.header_title')" :description="__('feed.header_description')" :icon="null">
            <x-slot:action>
                <div class="flex flex-wrap items-center gap-2">
                    <x-ui.button href="{{ route('saved.index') }}" variant="ghost" size="sm">{{ __('feed.saved') }}</x-ui.button>
                    <x-ui.button href="{{ route('explore.index') }}" variant="ghost" size="sm">{{ __('feed.explore') }}</x-ui.button>
                </div>
            </x-slot:action>
        </x-ui.page-header>
    </x-slot>

    <div class="grid gap-4 lg:grid-cols-[16rem_minmax(0,1fr)] xl:grid-cols-[16rem_minmax(0,1fr)_18rem]" data-feed-surface="warm-editorial" data-ui="feed-livewire-page">
        <livewire:feed.left-sidebar lazy />

        <main class="min-w-0 space-y-4" data-ui="feed-main-column">
            @if ($showWelcomeBanner)
                <x-ui.card padding="base">
                    <div class="flex items-start gap-3">
                        <x-ui.avatar :src="$user->avatar_url" :name="$user->name" :user="$user" size="lg"/>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-bold text-bark">Welcome to PetSocial, {{ \Illuminate\Support\Str::before((string) $user->name, ' ') }}!</p>
                            <p class="mt-1 text-sm leading-6 text-fur">Start exploring by reacting to posts, following more pets, or creating your first post.</p>
                        </div>
                        <form method="POST" action="{{ route('onboarding.welcome-banner.dismiss') }}">
                            @csrf
                            <button type="submit" class="inline-flex h-9 w-9 items-center justify-center rounded-[var(--radius-soft)] text-fur transition hover:bg-cream hover:text-bark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw" aria-label="Dismiss welcome banner">
                                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
                                    <path d="M5 5l10 10M15 5 5 15"/>
                                </svg>
                            </button>
                        </form>
                    </div>
                </x-ui.card>
            @endif

            @if ($showOnboardingPetReminder)
                <x-ui.card padding="base">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-sm leading-6 text-fur">Your profile is ready. Add your first pet when you have a moment so your feed can surface better pet matches.</p>
                        <x-ui.button href="#" x-on:click.prevent="window.openPetCreateWizard('onboarding-reminder')" variant="secondary" size="sm">Add a pet</x-ui.button>
                    </div>
                </x-ui.card>
            @endif

            <livewire:feed.stream :source="$source" :type="$type" />
        </main>

        <livewire:feed.right-sidebar lazy />
    </div>
</x-app-layout>
