<?php

namespace App\Models\Groups;

use App\Enums\GroupInvitationStatus;
use App\Enums\GroupMemberRole;
use App\Models\Identity\User;
use Carbon\CarbonInterface;
use Database\Factories\GroupInvitationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[UseFactory(GroupInvitationFactory::class)]
#[Fillable([
    'group_id',
    'invited_user_id',
    'invited_by_user_id',
    'status',
    'role',
    'message',
    'responded_at',
    'expires_at',
])]
class GroupInvitation extends Model
{
    /** @use HasFactory<GroupInvitationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => GroupInvitationStatus::class,
            'responded_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Group, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function invitedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    /**
     * @param  Builder<GroupInvitation>  $query
     * @return Builder<GroupInvitation>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', GroupInvitationStatus::Pending->value);
    }

    /**
     * @param  Builder<GroupInvitation>  $query
     * @return Builder<GroupInvitation>
     */
    public function scopeForGroup(Builder $query, int $groupId): Builder
    {
        return $query->where('group_id', $groupId);
    }

    /**
     * @param  Builder<GroupInvitation>  $query
     * @return Builder<GroupInvitation>
     */
    public function scopeForInvitedUser(Builder $query, int $userId): Builder
    {
        return $query->where('invited_user_id', $userId);
    }

    public function isPending(): bool
    {
        return $this->statusValue() === GroupInvitationStatus::Pending
            && ! $this->isExpired();
    }

    public function isExpired(): bool
    {
        return $this->expiresAt()?->isPast() ?? false;
    }

    public function roleValue(): string
    {
        $role = GroupMemberRole::tryFrom((string) $this->getAttribute('role'));

        return $role instanceof GroupMemberRole ? $role->value : GroupMemberRole::Member->value;
    }

    private function statusValue(): GroupInvitationStatus
    {
        return GroupInvitationStatus::tryFrom((string) $this->getAttribute('status')) ?? GroupInvitationStatus::Pending;
    }

    private function expiresAt(): ?CarbonInterface
    {
        $expiresAt = $this->getAttribute('expires_at');

        if ($expiresAt instanceof CarbonInterface) {
            return $expiresAt;
        }

        if (! is_string($expiresAt) || $expiresAt === '') {
            return null;
        }

        return Carbon::parse($expiresAt);
    }
}
