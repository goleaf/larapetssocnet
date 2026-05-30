<?php

use App\Http\Controllers\Pets\PetController;
use App\Models\Identity\User;
use App\Models\Pets\Pet;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

new
#[Layout('layouts.livewire-pass-through')]
class extends Component
{
    public Pet $pet;

    public string $activeTab = 'posts';

    private const ALLOWED_TABS = ['posts', 'gallery', 'milestones', 'adopt', 'about', 'health'];

    public function mount(Pet $pet): void
    {
        $this->pet = $this->loadPet($pet);

        Gate::authorize('view', $this->pet);

        $this->activeTab = $this->normalizeTab($this->resolveInitialTab());
        $this->hideUnavailableTabs();
    }

    public function activateTab(string $tab): void
    {
        $this->activeTab = $this->normalizeTab($tab);
        $this->hideUnavailableTabs();
        $this->storeLastVisitedTab();
    }

    public function render(): View
    {
        $this->pet = $this->loadPet($this->pet);

        request()->attributes->set('pet_active_tab', $this->activeTab);
        request()->attributes->set('pet_profile_livewire', true);

        $response = app(PetController::class)->show(request(), $this->pet);

        if ($response instanceof RedirectResponse) {
            throw new HttpResponseException($response);
        }

        return $response->layout('layouts.livewire-pass-through');
    }

    private function resolveInitialTab(): string
    {
        $requestedTab = request()->query('tab');

        if (is_string($requestedTab) && $requestedTab !== '') {
            return $requestedTab;
        }

        $storedTab = session()->get($this->lastVisitedTabSessionKey());

        return is_string($storedTab) ? $storedTab : 'posts';
    }

    private function hideUnavailableTabs(): void
    {
        $availableTabs = $this->availableTabs();

        if (in_array($this->activeTab, $availableTabs, true)) {
            return;
        }

        $this->activeTab = in_array('posts', $availableTabs, true) ? 'posts' : 'about';
    }

    /**
     * @return list<string>
     */
    private function availableTabs(): array
    {
        $viewer = request()->user() ?: auth()->user();
        $canViewTimelineContent = Gate::forUser($viewer instanceof User ? $viewer : null)->allows('viewPosts', $this->pet);

        $tabs = $canViewTimelineContent ? ['posts', 'gallery', 'milestones'] : ['about'];

        if (($this->pet->adoption_status ?? 'not_listed') !== 'not_listed' || (bool) $this->pet->is_adoptable) {
            $tabs[] = 'adopt';
        }

        if (! in_array('about', $tabs, true)) {
            $tabs[] = 'about';
        }

        if ($viewer instanceof User && $this->pet->isOwnedBy($viewer)) {
            $tabs[] = 'health';
        }

        return $tabs;
    }

    private function storeLastVisitedTab(): void
    {
        session()->put($this->lastVisitedTabSessionKey(), $this->activeTab);
    }

    private function lastVisitedTabSessionKey(): string
    {
        return sprintf('pets.%s.active_tab', $this->pet->getKey());
    }

    private function normalizeTab(string $tab): string
    {
        return in_array($tab, self::ALLOWED_TABS, true) ? $tab : 'posts';
    }

    private function loadPet(Pet $pet): Pet
    {
        return Pet::query()
            ->whereKey($pet->getKey())
            ->with(['user', 'species', 'breed', 'media', 'tags'])
            ->firstOrFail();
    }
};
?>
