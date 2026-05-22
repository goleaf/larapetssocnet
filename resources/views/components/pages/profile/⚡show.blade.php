<?php

use App\Http\Controllers\Profile\PublicProfileController;
use App\Models\Identity\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

new
#[Layout('layouts.livewire-pass-through')]
class extends Component
{
    public User $user;

    public function mount(User $user): void
    {
        $this->user = $user;

        $this->redirectRestrictedViewer();
        $this->abortBlockedViewer();
        $this->redirectCanonicalUsername();
    }

    public function render(): View
    {
        $response = app(PublicProfileController::class)->show(request(), $this->user);

        if ($response instanceof RedirectResponse) {
            throw new HttpResponseException($response);
        }

        return $response->layout('layouts.livewire-pass-through');
    }

    private function redirectRestrictedViewer(): void
    {
        $viewer = request()->user();

        if (! $viewer instanceof User) {
            return;
        }

        if ((bool) $viewer->is_banned) {
            throw new HttpResponseException(new RedirectResponse(route('banned')));
        }

        if ($viewer->hasPendingDeletion()) {
            throw new HttpResponseException(new RedirectResponse(route('account.deletion-pending')));
        }

        if ($viewer->isDeactivated()) {
            throw new HttpResponseException(new RedirectResponse(route('account.reactivation')));
        }

        if ($viewer->isSuspended()) {
            throw new HttpResponseException(new RedirectResponse(route('account.suspended')));
        }
    }

    private function abortBlockedViewer(): void
    {
        $viewer = request()->user();

        if ($viewer instanceof User && $viewer->hasBlockingRelationshipWith($this->user)) {
            abort(404);
        }
    }

    private function redirectCanonicalUsername(): void
    {
        $redirect = request()->attributes->get('username_redirect');

        if ($redirect) {
            $this->redirectToCanonicalProfile((string) $redirect->user->username);
        }

        $rawUsername = (string) request()->attributes->get('username_raw', $this->user->username);

        if ($rawUsername !== $this->user->username) {
            $this->redirectToCanonicalProfile((string) $this->user->username);
        }
    }

    private function redirectToCanonicalProfile(string $username): never
    {
        $target = route('profile.show', ['user' => $username]);
        $query = request()->getQueryString();

        if ($query) {
            $target .= '?'.$query;
        }

        throw new HttpResponseException(new RedirectResponse($target, 301));
    }
};
?>
