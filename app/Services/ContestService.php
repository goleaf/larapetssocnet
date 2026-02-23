<?php

namespace App\Services;

use App\Models\Contest;
use App\Models\ContestEntry;
use App\Models\ContestVote;
use App\Models\User;
use App\Notifications\ContestWinner;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ContestService
{
    public function create(User $organizer, array $data, ?UploadedFile $cover = null): Contest
    {
        return DB::transaction(function () use ($organizer, $data, $cover): Contest {
            $contest = Contest::create([
                'organizer_user_id' => $organizer->id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'prize' => $data['prize'] ?? null,
                'species' => $data['species'] ?? null,
                'starts_at' => $data['starts_at'],
                'ends_at' => $data['ends_at'],
                'status' => 'draft',
                'max_entries' => $data['max_entries'] ?? 1,
            ]);

            if ($cover) {
                $contest->addMedia($cover)
                    ->usingFileName(Str::uuid().'.webp')
                    ->toMediaCollection('cover');
            }

            return $contest;
        });
    }

    public function enter(User $user, Contest $contest, array $data, ?UploadedFile $photo = null): ContestEntry
    {
        if (! $contest->isActive()) {
            throw new RuntimeException('This contest is not currently accepting entries.');
        }

        if ((int) $contest->organizer_user_id === (int) $user->id) {
            throw new RuntimeException('Organizers cannot enter their own contest.');
        }

        if ($contest->hasEntered($user)) {
            throw new RuntimeException('You have already entered this contest.');
        }

        return DB::transaction(function () use ($user, $contest, $data, $photo): ContestEntry {
            $entry = ContestEntry::create([
                'contest_id' => $contest->id,
                'user_id' => $user->id,
                'pet_id' => $data['pet_id'] ?? null,
                'caption' => $data['caption'] ?? null,
            ]);

            if ($photo) {
                $entry->addMedia($photo)
                    ->usingFileName(Str::uuid().'.webp')
                    ->toMediaCollection('entry-photo');
            }

            $contest->increment('entries_count');

            return $entry;
        });
    }

    public function vote(User $user, Contest $contest, ContestEntry $entry): void
    {
        if ($contest->status !== 'voting') {
            throw new RuntimeException('This contest is not in the voting phase.');
        }

        if ((int) $entry->user_id === (int) $user->id) {
            throw new RuntimeException('You cannot vote for your own entry.');
        }

        if ($contest->hasVoted($user)) {
            throw new RuntimeException('You have already voted in this contest.');
        }

        DB::transaction(function () use ($user, $contest, $entry): void {
            ContestVote::create([
                'user_id' => $user->id,
                'contest_id' => $contest->id,
                'entry_id' => $entry->id,
            ]);

            $entry->increment('votes_count');
        });
    }

    public function pickWinner(Contest $contest, ContestEntry $entry, User $organizer): void
    {
        if ((int) $contest->organizer_user_id !== (int) $organizer->id) {
            throw new RuntimeException('Only the organizer can pick a winner.');
        }

        if ($contest->status !== 'voting') {
            throw new RuntimeException('Contest must be in voting phase to pick a winner.');
        }

        DB::transaction(function () use ($contest, $entry): void {
            $contest->update([
                'winner_entry_id' => $entry->id,
                'status' => 'ended',
            ]);

            $entry->updateQuietly(['is_winner' => true]);

            if ($entry->user->notificationEnabled('contest_updates')) {
                $entry->user->notify(new ContestWinner($contest, $entry));
            }
        });
    }

    public function transition(Contest $contest, string $status): void
    {
        $allowed = Contest::TRANSITIONS[$contest->status] ?? [];

        if (! in_array($status, $allowed, true)) {
            throw new RuntimeException(
                "Invalid contest transition: {$contest->status} → {$status}"
            );
        }

        $contest->update(['status' => $status]);
    }
}
