<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupMember extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'group_id',
        'user_id',
        'role',
        'status',
        'joined_at',
        'invited_by',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where(function (Builder $subQuery): void {
            $subQuery->whereNull('status')->orWhere('status', 'active');
        });
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeAdmins(Builder $query): Builder
    {
        return $query->whereIn('role', ['owner', 'admin']);
    }

    public function isAdmin(): bool
    {
        return in_array((string) $this->role, ['owner', 'admin'], true);
    }

    public function isModerator(): bool
    {
        return in_array((string) $this->role, ['owner', 'admin', 'moderator'], true);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
