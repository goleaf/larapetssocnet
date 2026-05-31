<?php

namespace Database\Seeders;

use App\Models\Activities\Contest;
use App\Models\Activities\ContestEntry;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ContestSeeder extends Seeder
{
    public function run(): void
    {
        $userIds = DB::table('users')->pluck('id')->all();
        $petByUser = DB::table('pets')
            ->select(['id', 'user_id'])
            ->get()
            ->groupBy('user_id')
            ->map(fn ($rows) => $rows->pluck('id')->all())
            ->all();

        if ($userIds === []) {
            return;
        }

        $faker = fake();
        $contestIds = [];

        for ($i = 0; $i < 5; $i++) {
            $slug = 'pet-contest-'.($i + 1);
            $startsAt = Carbon::instance($faker->dateTimeBetween('-20 days', '+20 days'));
            $endsAt = (clone $startsAt)->addDays(random_int(3, 12));
            $status = $faker->randomElement(['active', 'voting', 'closed']);

            $contest = Contest::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'organizer_user_id' => $userIds[array_rand($userIds)],
                    'title' => 'Pet Contest '.($i + 1),
                    'slug' => $slug,
                    'description' => $faker->sentence(),
                    'prize' => '$'.number_format(random_int(50, 500), 0),
                    'species' => $faker->randomElement(['dog', 'cat', 'bird', null]),
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'max_entries' => random_int(8, 30),
                    'entries_count' => 0,
                    'winner_entry_id' => null,
                    'status' => $status,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            $contestIds[] = (int) $contest->getKey();
        }

        foreach ($contestIds as $contestId) {
            $entryUserCount = random_int(0, min(5, count($userIds)));
            $entryUsers = $this->pickRandomUnique($userIds, $entryUserCount);
            $entryIds = [];

            foreach ($entryUsers as $entryUserId) {
                $petIds = $petByUser[$entryUserId] ?? [];
                if ($petIds === []) {
                    continue;
                }

                $entry = ContestEntry::query()->updateOrCreate(
                    [
                        'contest_id' => $contestId,
                        'user_id' => $entryUserId,
                    ],
                    [
                        'pet_id' => $petIds[array_rand($petIds)],
                        'post_id' => null,
                        'caption' => $faker->sentence(),
                        'votes_count' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );

                $entryIds[] = (int) $entry->getKey();
            }

            $votes = [];
            foreach ($entryIds as $entryId) {
                foreach ($this->pickRandomUnique($userIds, random_int(0, min(10, count($userIds)))) as $voterId) {
                    $votes[] = [
                        'entry_id' => $entryId,
                        'user_id' => $voterId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            if ($votes !== []) {
                foreach (array_chunk($votes, 400) as $chunk) {
                    DB::table('contest_votes')->insertOrIgnore($chunk);
                }
            }
        }

        DB::statement('UPDATE contests SET entries_count = (SELECT COUNT(*) FROM contest_entries WHERE contest_entries.contest_id = contests.id)');
        DB::statement('UPDATE contest_entries SET votes_count = (SELECT COUNT(*) FROM contest_votes WHERE contest_votes.entry_id = contest_entries.id)');
    }

    /**
     * @param  list<int>  $source
     * @return list<int>
     */
    private function pickRandomUnique(array $source, int $count): array
    {
        if ($source === []) {
            return [];
        }

        shuffle($source);

        return array_slice($source, 0, min($count, count($source)));
    }
}
