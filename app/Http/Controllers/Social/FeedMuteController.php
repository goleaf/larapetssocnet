<?php

namespace App\Http\Controllers\Social;

use App\Http\Controllers\Controller;
use App\Http\Requests\Social\StoreFeedMuteRequest;
use App\Models\Identity\User;
use App\Models\Pets\Pet;
use App\Models\Social\FeedMute;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FeedMuteController extends Controller
{
    public function index(Request $request): View
    {
        $mutedAccounts = $request->user()
            ->feedMutes()
            ->with('mutable')
            ->latest()
            ->paginate(20);

        return view('settings.muted', [
            'mutedAccounts' => $mutedAccounts,
        ]);
    }

    public function store(StoreFeedMuteRequest $request): RedirectResponse
    {
        $target = $request->target();

        abort_unless($target instanceof User || $target instanceof Pet, 422);

        FeedMute::query()->firstOrCreate([
            'user_id' => $request->user()->getKey(),
            'mutable_type' => $target->getMorphClass(),
            'mutable_id' => $target->getKey(),
        ]);

        return back()->with('success', "Muted {$request->targetLabel()} in your feed.");
    }

    public function destroy(Request $request, FeedMute $feedMute): RedirectResponse
    {
        abort_unless((int) $feedMute->user_id === (int) $request->user()->getKey(), 403);

        $target = $feedMute->mutable;
        $label = $target instanceof User
            ? '@'.($target->username ?: $target->name)
            : ($target instanceof Pet ? (string) $target->name : 'that source');

        $feedMute->delete();

        return back()->with('success', "Unmuted {$label}.");
    }
}
