<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * Seed the application's database roles.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'manage_users',
            'ban_users',
            'delete_any_post',
            'review_reports',
            'manage_groups',
            'manage_events',
            'feature_posts',
            'manage_marketplace',
            'award_badges',
            'manage_contests',
        ];

        foreach ($permissions as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');
        }

        $admin = Role::findOrCreate('admin', 'web');
        $moderator = Role::findOrCreate('moderator', 'web');
        Role::findOrCreate('verified_owner', 'web');
        $member = Role::findOrCreate('member', 'web');

        $permissionModels = Permission::query()
            ->whereIn('name', $permissions)
            ->where('guard_name', 'web')
            ->get();

        $admin->syncPermissions($permissionModels);
        $moderator->syncPermissions(
            $permissionModels->whereIn('name', [
                'review_reports',
                'ban_users',
                'delete_any_post',
                'manage_groups',
                'manage_events',
            ])->values()
        );
        $member->syncPermissions([]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
