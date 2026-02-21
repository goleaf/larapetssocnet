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

    public function scopeRole(Builder $query, string $role): Builder
    {
        return $query->where('role', $role);
    }

    public function scopeManagers(Builder $query): Builder
    {
        return $query->whereIn('role', ['owner', 'admin']);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where(function (Builder $statusQuery): void {
            $statusQuery
                ->whereNull('status')
                ->orWhereIn('status', ['active', 'accepted']);
        });
    }
}
