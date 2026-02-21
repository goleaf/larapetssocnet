<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EventSeeder extends Seeder
{
    private const TARGET_EVENT_COUNT = 45;

    /**
     * Seed events and attendees.
     */
    public function run(): void
    {
        $userIds = DB::table('users')->pluck('id')->all();
        $groupIds = DB::table('groups')->pluck('id')->all();

        if ($userIds === []) {
            return;
        }

        $groupMembers = DB::table('group_members')
            ->where('status', 'active')
            ->get(['group_id', 'user_id'])
            ->groupBy('group_id')
            ->map(fn ($rows) => $rows->pluck('user_id')->all())
            ->all();

        $faker = fake();
        $events = [];

        for ($i = 0; $i < self::TARGET_EVENT_COUNT; $i++) {
            $groupId = null;

            if ($groupIds !== [] && random_int(1, 100) <= 70) {
                $groupId = $groupIds[array_rand($groupIds)];
            }

            $candidateCreators = $groupId !== null
                ? ($groupMembers[$groupId] ?? $userIds)
                : $userIds;

            $creatorId = $candidateCreators[array_rand($candidateCreators)];

            $startAt = Carbon::instance($faker->dateTimeBetween('-20 days', '+60 days'));
            $endAt = random_int(1, 100) <= 85
                ? (clone $startAt)->addHours(random_int(1, 5))
                : null;

            $status = $startAt->isPast() ? 'completed' : 'scheduled';

            if (!$startAt->isPast() && random_int(1, 100) <= 12) {
                $status = 'cancelled';
            }

            $eventId = DB::table('events')->insertGetId([
                'group_id' => $groupId,
                'creator_user_id' => $creatorId,
                'title' => $faker->sentence(random_int(3, 7)),
                'description' => random_int(1, 100) <= 85 ? $faker->paragraph() : null,
                'location_text' => random_int(1, 100) <= 85 ? $faker->city() : null,
                'start_at' => $startAt,
                'end_at' => $endAt,
                'status' => $status,
                'attendees_count' => 0,
                'created_at' => $startAt,
                'updated_at' => $startAt,
            ]);

            $events[] = [
                'id' => $eventId,
                'creator_id' => $creatorId,
                'start_at' => $startAt,
            ];
        }

        $attendees = [];

        foreach ($events as $event) {
            $maxAttendees = min(30, count($userIds));
            $minAttendees = min(6, $maxAttendees);
            $attendeeCount = random_int($minAttendees, $maxAttendees);

            $attendeeIds = array_values(array_unique(array_merge(
                [$event['creator_id']],
                $this->pickRandomUnique($userIds, $attendeeCount)
            )));

            foreach ($attendeeIds as $attendeeId) {
                $status = $attendeeId === $event['creator_id']
                    ? 'going'
                    : $this->randomAttendeeStatus();

                $respondedAt = Carbon::instance($faker->dateTimeBetween('-20 days', 'now'));

                $attendees[] = [
                    'event_id' => $event['id'],
                    'user_id' => $attendeeId,
                    'status' => $status,
                    'responded_at' => $respondedAt,
                    'created_at' => $respondedAt,
                    'updated_at' => $respondedAt,
                ];
            }
        }

        if ($attendees !== []) {
            foreach (array_chunk($attendees, 500) as $chunk) {
                DB::table('event_attendees')->insertOrIgnore($chunk);
            }
        }

        DB::statement("UPDATE events SET attendees_count = (SELECT COUNT(*) FROM event_attendees WHERE event_attendees.event_id = events.id AND event_attendees.status = 'going')");
    }

    private function randomAttendeeStatus(): string
    {
        $roll = random_int(1, 100);

        if ($roll <= 70) {
            return 'going';
        }

        if ($roll <= 90) {
            return 'interested';
        }

        return 'not_going';
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
