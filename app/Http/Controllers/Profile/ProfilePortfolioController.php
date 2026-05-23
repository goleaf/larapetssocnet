<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdateProfilePortfolioRequest;
use App\Models\Identity\User;
use App\Services\ProfilePortfolioService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfilePortfolioController extends Controller
{
    public function __invoke(Request $request, User $user, ProfilePortfolioService $portfolio): View|RedirectResponse
    {
        $viewer = $request->user();

        if ($viewer instanceof User) {
            $restrictedRedirect = $this->restrictedViewerRedirect($viewer);

            if ($restrictedRedirect instanceof RedirectResponse) {
                return $restrictedRedirect;
            }
        }

        if ($user->isUnavailableForProfile()) {
            abort(404);
        }

        if ($viewer instanceof User && $viewer->hasBlockingRelationshipWith($user)) {
            abort(404);
        }

        $redirect = $request->attributes->get('username_redirect');

        if ($redirect) {
            return redirect()->route('profile.portfolio', ['user' => $redirect->user->username], 301);
        }

        $rawUsername = (string) $request->attributes->get('username_raw', $user->username);

        if ($rawUsername !== $user->username) {
            return redirect()->route('profile.portfolio', ['user' => $user->username], 301);
        }

        $user->loadMissing('media');

        return view('profile.portfolio', [
            'profileUser' => $user,
            'portfolioPosts' => $portfolio->publicPosts($user),
            'portfolioUrl' => route('profile.portfolio', ['user' => $user->username]),
        ]);
    }

    public function update(UpdateProfilePortfolioRequest $request, ProfilePortfolioService $portfolio): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $portfolio->sync($user, $request->portfolioPostIds(), $request->portfolioPositions());

        return back()->with('success', 'Portfolio updated.');
    }

    private function restrictedViewerRedirect(User $viewer): ?RedirectResponse
    {
        if ((bool) $viewer->is_banned) {
            return redirect()->route('banned');
        }

        if ($viewer->hasPendingDeletion()) {
            return redirect()->route('account.deletion-pending');
        }

        if ($viewer->isDeactivated()) {
            return redirect()->route('account.reactivation');
        }

        if ($viewer->isSuspended()) {
            return redirect()->route('account.suspended');
        }

        return null;
    }
}
