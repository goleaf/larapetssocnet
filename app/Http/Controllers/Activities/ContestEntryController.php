<?php

namespace App\Http\Controllers\Activities;

use App\Http\Controllers\Controller;
use App\Models\Activities\Contest;
use App\Services\ContestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ContestEntryController extends Controller
{
    public function store(Request $request, Contest $contest): RedirectResponse
    {
        $data = $request->validate([
            'pet_id' => ['nullable', 'exists:pets,id'],
            'caption' => ['nullable', 'string', 'max:500'],
            'photo' => ['required', 'image', 'max:5120'],
        ]);

        app(ContestService::class)->enter(
            auth()->user(),
            $contest,
            $data,
            $request->file('photo'),
        );

        return redirect()->route('contests.show', $contest->slug)
            ->with('success', 'Entry submitted!');
    }
}
