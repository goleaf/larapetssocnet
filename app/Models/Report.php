<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Report extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_REVIEWED = 'reviewed';

    public const STATUS_DISMISSED = 'dismissed';

    public const STATUS_ACTIONED = 'actioned';

    public const STATUS_RESOLVED = 'resolved';

    public const REASON_SPAM = 'spam';

    public const REASON_HARASSMENT = 'harassment';

    public const REASON_HATE_SPEECH = 'hate_speech';

    public const REASON_MISINFORMATION = 'misinformation';

    public const REASON_NUDITY = 'nudity';

    public const REASON_VIOLENCE = 'violence';

    public const REASON_OTHER = 'other';

    public const REASON_ABUSE = 'abuse';

    /**
     * @var list<string>
     */
    public const REASONS = [
        self::REASON_SPAM,
        self::REASON_HARASSMENT,
        self::REASON_HATE_SPEECH,
        self::REASON_MISINFORMATION,
        self::REASON_NUDITY,
        self::REASON_VIOLENCE,
        self::REASON_OTHER,
        self::REASON_ABUSE,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'reporter_user_id',
        'reportable_type',
        'reportable_id',
        'reason',
        'details',
        'status',
        'reviewed_by_user_id',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_user_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function reportable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where(function (Builder $subQuery): void {
            $subQuery
                ->whereNull('status')
                ->orWhere('status', self::STATUS_PENDING);
        });
    }

    public function scopeResolved(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_RESOLVED, self::STATUS_ACTIONED, self::STATUS_DISMISSED, self::STATUS_REVIEWED]);
    }

    public function resolve(User $resolver, ?string $notes = null): bool
    {
        $this->forceFill([
            'status' => self::STATUS_RESOLVED,
            'reviewed_by_user_id' => $resolver->getKey(),
            'reviewed_at' => now(),
            'details' => $notes ?: $this->details,
        ]);

        return $this->save();
    }
}
