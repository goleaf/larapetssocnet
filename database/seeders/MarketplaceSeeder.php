<?php

namespace Database\Seeders;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MarketplaceSeeder extends Seeder
{
    private const TARGET_LISTING_COUNT = 90;
    private const TARGET_MESSAGE_COUNT = 700;
    private const TARGET_REPORT_COUNT = 85;

    /**
     * Seed marketplace listings, direct messages, and moderation reports.
     */
    public function run(): void
    {
        $users = DB::table('users')->get(['id', 'email']);

        if ($users->isEmpty()) {
            return;
        }

        $userIds = $users->pluck('id')->all();
        $userEmailById = $users->pluck('email', 'id')->all();
        $petsByUser = DB::table('pets')
            ->get(['id', 'user_id'])
            ->groupBy('user_id')
            ->map(fn ($rows) => $rows->pluck('id')->all())
            ->all();

        $faker = fake();
        $listingIds = [];

        for ($i = 0; $i < self::TARGET_LISTING_COUNT; $i++) {
            $userId = $userIds[array_rand($userIds)];
            $userPetIds = $petsByUser[$userId] ?? [];
            $petId = null;

            if ($userPetIds !== [] && random_int(1, 100) <= 55) {
                $petId = $userPetIds[array_rand($userPetIds)];
            }

            $listingType = ['adoption', 'sale', 'service'][array_rand(['adoption', 'sale', 'service'])];
            $status = $this->randomListingStatus();
            $createdAt = Carbon::instance($faker->dateTimeBetween('-75 days', 'now'));

            $listingIds[] = DB::table('marketplace_listings')->insertGetId([
                'user_id' => $userId,
                'pet_id' => $petId,
                'title' => $faker->sentence(random_int(3, 8)),
                'description' => $faker->paragraph(random_int(2, 5)),
                'price' => $listingType === 'adoption' && random_int(1, 100) <= 65
                    ? null
                    : $faker->randomFloat(2, 20, 5000),
                'currency' => 'USD',
                'listing_type' => $listingType,
                'status' => $status,
                'location_text' => random_int(1, 100) <= 85 ? $faker->city() : null,
                'contact_phone' => random_int(1, 100) <= 60 ? $faker->phoneNumber() : null,
                'contact_email' => random_int(1, 100) <= 85 ? ($userEmailById[$userId] ?? $faker->safeEmail()) : null,
                'views_count' => random_int(0, 600),
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }

        $messages = [];

        for ($i = 0; $i < self::TARGET_MESSAGE_COUNT; $i++) {
            $senderId = $userIds[array_rand($userIds)];
            $recipientId = $userIds[array_rand($userIds)];

            while ($recipientId === $senderId) {
                $recipientId = $userIds[array_rand($userIds)];
            }

            $sentAt = Carbon::instance($faker->dateTimeBetween('-45 days', 'now'));
            $readAt = random_int(1, 100) <= 68
                ? Carbon::instance($faker->dateTimeBetween($sentAt, 'now'))
                : null;

            $messages[] = [
                'sender_user_id' => $senderId,
                'recipient_user_id' => $recipientId,
                'body' => $faker->sentence(random_int(4, 14)),
                'sent_at' => $sentAt,
                'read_at' => $readAt,
                'created_at' => $sentAt,
                'updated_at' => $sentAt,
            ];
        }

        foreach (array_chunk($messages, 500) as $chunk) {
            DB::table('messages')->insert($chunk);
        }

        $moderatorIds = $this->moderatorUserIds();
        $reportTargets = $this->reportTargets($listingIds);
        $reports = [];

        for ($i = 0; $i < self::TARGET_REPORT_COUNT; $i++) {
            $target = $this->pickReportTarget($reportTargets);

            if ($target === null) {
                break;
            }

            $status = $this->randomReportStatus();
            $createdAt = Carbon::instance($faker->dateTimeBetween('-40 days', 'now'));

            $reviewedBy = null;
            $reviewedAt = null;

            if ($status !== 'pending' && $moderatorIds !== []) {
                $reviewedBy = $moderatorIds[array_rand($moderatorIds)];
                $reviewedAt = Carbon::instance($faker->dateTimeBetween($createdAt, 'now'));
            }

            $reports[] = [
                'reporter_user_id' => $userIds[array_rand($userIds)],
                'reportable_type' => $target['type'],
                'reportable_id' => $target['id'],
                'reason' => $faker->randomElement([
                    'spam',
                    'harassment',
                    'inappropriate_content',
                    'misinformation',
                    'scam',
                ]),
                'details' => random_int(1, 100) <= 75 ? $faker->sentence() : null,
                'status' => $status,
                'reviewed_by_user_id' => $reviewedBy,
                'reviewed_at' => $reviewedAt,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ];
        }

        if ($reports !== []) {
            foreach (array_chunk($reports, 300) as $chunk) {
                DB::table('reports')->insert($chunk);
            }
        }
    }

    private function randomListingStatus(): string
    {
        $roll = random_int(1, 100);

        if ($roll <= 70) {
            return 'active';
        }

        if ($roll <= 85) {
            return 'paused';
        }

        return 'closed';
    }

    private function randomReportStatus(): string
    {
        $roll = random_int(1, 100);

        if ($roll <= 55) {
            return 'pending';
        }

        if ($roll <= 82) {
            return 'reviewed';
        }

        return 'resolved';
    }

    /**
     * @return array<int, array{type: string, ids: list<int>}>
     */
    private function reportTargets(array $listingIds): array
    {
        return [
            ['type' => 'App\\Models\\Post', 'ids' => DB::table('posts')->pluck('id')->all()],
            ['type' => 'App\\Models\\Comment', 'ids' => DB::table('comments')->pluck('id')->all()],
            ['type' => 'App\\Models\\Group', 'ids' => DB::table('groups')->pluck('id')->all()],
            ['type' => 'App\\Models\\MarketplaceListing', 'ids' => $listingIds],
            ['type' => 'App\\Models\\User', 'ids' => DB::table('users')->pluck('id')->all()],
        ];
    }

    /**
     * @param  array<int, array{type: string, ids: list<int>}>  $targets
     * @return array{type: string, id: int}|null
     */
    private function pickReportTarget(array $targets): ?array
    {
        $nonEmptyTargets = array_values(array_filter(
            $targets,
            static fn (array $target) => $target['ids'] !== []
        ));

        if ($nonEmptyTargets === []) {
            return null;
        }

        $target = $nonEmptyTargets[array_rand($nonEmptyTargets)];

        return [
            'type' => $target['type'],
            'id' => $target['ids'][array_rand($target['ids'])],
        ];
    }

    /**
     * @return list<int>
     */
    private function moderatorUserIds(): array
    {
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');

        $rolesTable = $tableNames['roles'];
        $modelHasRolesTable = $tableNames['model_has_roles'];
        $rolePivotKey = $columnNames['role_pivot_key'] ?? 'role_id';
        $modelMorphKey = $columnNames['model_morph_key'] ?? 'model_id';

        return DB::table($modelHasRolesTable)
            ->join($rolesTable, $modelHasRolesTable.'.'.$rolePivotKey, '=', $rolesTable.'.id')
            ->where($modelHasRolesTable.'.model_type', User::class)
            ->whereIn($rolesTable.'.name', ['admin', 'moderator'])
            ->pluck($modelHasRolesTable.'.'.$modelMorphKey)
            ->map(static fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
