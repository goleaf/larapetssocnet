<?php

namespace App\Http\Controllers\Activities;

use App\Http\Controllers\Controller;
use App\Models\Activities\Contest;
use App\Models\Activities\ContestEntry;
use App\Services\ContestService;
use Illuminate\Http\RedirectResponse;

class ContestVoteController extends Controller
{
    public function store(Contest $contest, ContestEntry $entry): RedirectResponse
    {
        app(ContestService::class)->vote(auth()->user(), $contest, $entry);

        return redirect()->route('contests.show', $contest->slug)
            ->with('success', 'Vote cast!');
    }

    public function pickWinner(Contest $contest, ContestEntry $entry): RedirectResponse
    {
        app(ContestService::class)->pickWinner($contest, $entry, auth()->user());

        return redirect()->route('contests.show', $contest->slug)
            ->with('success', 'Winner picked!');
    }
}
