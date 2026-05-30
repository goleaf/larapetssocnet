<?php

namespace App\Livewire\Pages\Dashboard;

use App\Models\Identity\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.livewire-pass-through')]
#[Title('Dashboard')]
class Index extends Component
{
    #[Computed]
    public function viewer(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }

    #[Computed]
    public function firstName(): string
    {
        $name = $this->viewer?->name;

        return filled($name) ? (string) Str::of((string) $name)->before(' ') : __('friend');
    }

    #[Computed]
    public function profileHref(): string
    {
        return $this->viewer instanceof User && Route::has('profile.show')
            ? route('profile.show', $this->viewer)
            : '#';
    }

    #[Computed]
    public function createPostHref(): string
    {
        return Route::has('posts.create') ? route('posts.create') : route('feed.index');
    }

    #[Computed]
    public function explorePetsHref(): string
    {
        return Route::has('pets.explore') ? route('pets.explore') : route('feed.index');
    }

    /**
     * @return Collection<int, array{key: string, label: string, description: string, href: string, icon: string, tone: string}>
     */
    #[Computed]
    public function quickActions(): Collection
    {
        return collect([
            [
                'key' => 'share-update',
                'label' => __('Share an update'),
                'description' => __('Post a photo, care note, or adoption story.'),
                'href' => Route::has('posts.create') ? route('posts.create') : null,
                'icon' => "\u{271A}",
                'tone' => 'bg-paw-light text-paw-dark',
            ],
            [
                'key' => 'add-pet',
                'label' => __('Add a pet'),
                'description' => __('Create a pet profile with photos and details.'),
                'href' => Route::has('pets.create') ? route('pets.create') : null,
                'icon' => "\u{1F43E}",
                'tone' => 'bg-leaf-light text-leaf',
            ],
            [
                'key' => 'find-groups',
                'label' => __('Find groups'),
                'description' => __('Join communities by interest, location, or species.'),
                'href' => Route::has('groups.index') ? route('groups.index') : null,
                'icon' => "\u{1F465}",
                'tone' => 'bg-sky-light text-sky',
            ],
            [
                'key' => 'browse-adoption',
                'label' => __('Browse adoption'),
                'description' => __('See pets currently looking for a home.'),
                'href' => Route::has('pets.adopt') ? route('pets.adopt') : null,
                'icon' => "\u{1F3E1}",
                'tone' => 'bg-amber-light text-amber',
            ],
            [
                'key' => 'open-messages',
                'label' => __('Open messages'),
                'description' => __('Continue conversations with pet people.'),
                'href' => Route::has('messages.index') ? route('messages.index') : null,
                'icon' => "\u{2709}",
                'tone' => 'bg-rose-light text-rose',
            ],
            [
                'key' => 'marketplace',
                'label' => __('Marketplace'),
                'description' => __('List supplies or discover local pet items.'),
                'href' => Route::has('marketplace.index') ? route('marketplace.index') : null,
                'icon' => "\u{1F6CD}",
                'tone' => 'bg-cream text-fur',
            ],
        ])
            ->filter(fn (array $action): bool => filled($action['href']))
            ->values();
    }

    public function render(): View
    {
        return view('livewire.pages.dashboard.index');
    }
}
