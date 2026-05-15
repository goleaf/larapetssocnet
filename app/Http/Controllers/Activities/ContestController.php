<?php

namespace App\Http\Controllers\Activities;

use App\Http\Controllers\Controller;
use App\Models\Activities\Contest;
use App\Services\ContestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContestController extends Controller
{
    public function __construct(
        private readonly ContestService $contestService,
    ) {}

    public function index(): View
    {
        $contests = Contest::visible()
            ->with(['organizer.media', 'media'])
            ->withCount('entries')
            ->latest('starts_at')
            ->paginate(12);

        return view('activities.contests.index', ['contests' => $contests]);
    }

    public function show(Contest $contest): View
    {
        $contest->load([
            'organizer.media',
            'entries' => fn ($q) => $q->with(['user.media', 'pet.media', 'media'])->orderByDesc('votes_count'),
            'winner.user.media',
        ]);

        $userEntry = auth()->check()
            ? $contest->entries()->where('user_id', auth()->id())->first()
            : null;

        $hasVoted = auth()->check() && $contest->hasVoted(auth()->user());

        return view('activities.contests.show', ['contest' => $contest, 'userEntry' => $userEntry, 'hasVoted' => $hasVoted]);
    }

    public function create(): View
    {
        return view('activities.contests.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'prize' => ['nullable', 'string', 'max:255'],
            'species' => ['nullable', 'string', 'max:20'],
            'starts_at' => ['required', 'date', 'after:now'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'max_entries' => ['nullable', 'integer', 'min:1', 'max:10'],
            'cover' => ['nullable', 'image', 'max:10240'],
        ]);

        $contest = $this->contestService->create(
            auth()->user(),
            $data,
            $request->file('cover'),
        );

        return redirect()->route('contests.show', $contest->slug)
            ->with('success', 'Contest created!');
    }

    public function edit(Contest $contest): View
    {
        $this->authorize('update', $contest);

        return view('activities.contests.edit', ['contest' => $contest]);
    }

    public function update(Request $request, Contest $contest): RedirectResponse
    {
        $this->authorize('update', $contest);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'prize' => ['nullable', 'string', 'max:255'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
        ]);

        $contest->update($data);

        return redirect()->route('contests.show', $contest->slug)
            ->with('success', 'Contest updated.');
    }

    public function destroy(Contest $contest): RedirectResponse
    {
        $this->authorize('delete', $contest);

        $contest->delete();

        return redirect()->route('contests.index')
            ->with('success', 'Contest deleted.');
    }
}
