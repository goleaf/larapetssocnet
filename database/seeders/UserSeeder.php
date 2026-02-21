<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public const DEFAULT_ADMIN_EMAIL = 'admin@larapetssocnet.test';
    public const DEFAULT_MODERATOR_EMAIL = 'mod@larapetssocnet.test';

    private const TARGET_USER_COUNT = 62;

    /**
     * Seed the application's users.
     */
    public function run(): void
    {
        $adminEmail = (string) env('SEED_ADMIN_EMAIL', self::DEFAULT_ADMIN_EMAIL);
        $moderatorEmail = (string) env('SEED_MODERATOR_EMAIL', self::DEFAULT_MODERATOR_EMAIL);

        $admin = $this->upsertPrivilegedUser(
            email: $adminEmail,
            name: 'Admin User',
            username: 'admin',
            interests: 'moderation, safety, community, platform quality'
        );

        $moderator = $this->upsertPrivilegedUser(
            email: $moderatorEmail,
            name: 'Moderator User',
            username: 'moderator',
            interests: 'community, support, safety, content review'
        );

        $missingUsers = max(0, self::TARGET_USER_COUNT - User::query()->count());

        if ($missingUsers > 0) {
            User::factory($missingUsers)->create();
        }

        $this->assignRoles($admin->id, $moderator->id);
    }

    private function upsertPrivilegedUser(
        string $email,
        string $name,
        string $username,
        string $interests
    ): User {
        $user = User::query()->firstOrNew(['email' => $email]);

        $user->forceFill([
            'name' => $name,
            'username' => $username,
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'bio' => $name.' account for PetSocial seed data.',
            'avatar_path' => null,
            'city' => 'New York',
            'country_code' => 'US',
            'is_private' => false,
            'last_seen_at' => now(),
            'onboarding_step' => 'complete',
            'interests_text' => $interests,
            'followers_count' => 0,
            'following_count' => 0,
            'pets_count' => 0,
            'posts_count' => 0,
        ]);

        $user->save();

        return $user->refresh();
    }

    private function assignRoles(int $adminUserId, int $moderatorUserId): void
    {
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');

        $rolesTable = $tableNames['roles'];
        $modelHasRolesTable = $tableNames['model_has_roles'];
        $rolePivotKey = $columnNames['role_pivot_key'] ?? 'role_id';
        $modelMorphKey = $columnNames['model_morph_key'] ?? 'model_id';

        $roleIds = DB::table($rolesTable)
            ->whereIn('name', ['admin', 'moderator', 'user'])
            ->pluck('id', 'name');

        if ($roleIds->isEmpty()) {
            return;
        }

        DB::table($modelHasRolesTable)
            ->where('model_type', User::class)
            ->delete();

        $users = User::query()->pluck('id');
        $rows = [];

        foreach ($users as $userId) {
            $roleId = $roleIds['user'] ?? null;

            if ($userId === $adminUserId) {
                $roleId = $roleIds['admin'] ?? $roleId;
            }

            if ($userId === $moderatorUserId) {
                $roleId = $roleIds['moderator'] ?? $roleId;
            }

            if ($roleId === null) {
                continue;
            }

            $rows[] = [
                $rolePivotKey => $roleId,
                'model_type' => User::class,
                $modelMorphKey => $userId,
            ];
        }

        if ($rows !== []) {
            DB::table($modelHasRolesTable)->insertOrIgnore($rows);
        }
    }
}
