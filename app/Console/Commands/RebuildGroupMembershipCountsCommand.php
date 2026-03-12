<?php

namespace App\Console\Commands;

use App\Enums\GroupMemberRole;
use App\Enums\GroupMemberStatus;
use App\Models\Group;
use App\Models\GroupMember;
use App\Services\SyncGroupCountersService;
use Illuminate\Console\Command;

class RebuildGroupMembershipCountsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'groups:rebuild-memberships {--chunk=100 : Number of groups per chunk}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rebuild group membership counts and ensure owner memberships are aligned.';

    /**
     * Execute the console command.
     */
    public function handle(SyncGroupCountersService $counters): int
    {
        $chunkSize = max(1, (int) $this->option('chunk'));

        Group::query()
            ->select(['id', 'owner_id', 'owner_user_id'])
            ->chunkById($chunkSize, function ($groups) use ($counters): void {
                foreach ($groups as $group) {
                    $ownerId = (int) ($group->owner_user_id ?? $group->owner_id ?? 0);
                    if ($ownerId > 0) {
                        $membership = GroupMember::query()
                            ->where('group_id', $group->getKey())
                            ->where('user_id', $ownerId)
                            ->first();

                        if (! $membership) {
                            GroupMember::query()->create([
                                'group_id' => $group->getKey(),
                                'user_id' => $ownerId,
                                'role' => GroupMemberRole::Owner->value,
                                'status' => GroupMemberStatus::Active->value,
                                'joined_at' => now(),
                            ]);
                        } else {
                            $updates = [];

                            if ((string) ($membership->role?->value ?? '') !== GroupMemberRole::Owner->value) {
                                $updates['role'] = GroupMemberRole::Owner->value;
                            }

                            if ((string) ($membership->status?->value ?? '') !== GroupMemberStatus::Active->value) {
                                $updates['status'] = GroupMemberStatus::Active->value;
                            }

                            if (! $membership->joined_at) {
                                $updates['joined_at'] = now();
                            }

                            if ($updates !== []) {
                                $membership->forceFill($updates)->save();
                            }
                        }
                    }

                    $counters->syncMembersCount($group);
                }
            });

        $this->info('Group memberships rebuilt.');

        return self::SUCCESS;
    }
}
