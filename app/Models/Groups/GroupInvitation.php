<?php

namespace App\Models\Groups;

use App\Enums\GroupInvitationStatus;
use App\Enums\GroupMemberRole;
use App\Models\Identity\User;
use Database\Factories\GroupInvitationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => GroupInvitationStatus::class,
            'responded_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function invitedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_user_id');
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', GroupInvitationStatus::Pending->value);
    }

    public function scopeForGroup(Builder $query, int $groupId): Builder
    {
        return $query->where('group_id', $groupId);
    }

    public function scopeForInvitedUser(Builder $query, int $userId): Builder
    {
        return $query->where('invited_user_id', $userId);
    }

    public function isPending(): bool
    {
        return $this->status === GroupInvitationStatus::Pending
            && ! $this->isExpired();
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function roleValue(): string
    {
        return GroupMemberRole::tryFrom((string) $this->role)?->value ?? GroupMemberRole::Member->value;
    }
}
