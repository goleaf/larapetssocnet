<?php

namespace App\Models\Moderation;

use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'reporter_user_id',
    'reportable_type',
    'reportable_id',
    'reason',
    'details',
    'status',
    'priority',
    'reviewed_by_user_id',
    'reviewed_at',
])]
class Report extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_REVIEWED = 'reviewed';

    public const STATUS_DISMISSED = 'dismissed';

    public const STATUS_ACTIONED = 'actioned';

    public const STATUS_RESOLVED = 'resolved';

    public const PRIORITY_NORMAL = 'normal';

    public const PRIORITY_HIGH = 'high';

    public const REASON_SPAM = 'spam';

    public const REASON_HARASSMENT = 'harassment';

    public const REASON_HATE_SPEECH = 'hate_speech';

    public const REASON_MISINFORMATION = 'misinformation';

    public const REASON_NUDITY = 'nudity';

    public const REASON_VIOLENCE = 'violence';

    public const REASON_OTHER = 'other';

    public const REASON_ABUSE = 'abuse';

    public const PROFILE_REASON_IMPERSONATION = 'profile_impersonation';

    public const PROFILE_REASON_FAKE_OR_MISLEADING = 'profile_fake_or_misleading';

    public const PROFILE_REASON_INAPPROPRIATE_CONTENT = 'profile_inappropriate_content';

    public const PROFILE_REASON_SPAM_ACCOUNT = 'profile_spam_account';

    public const PROFILE_REASON_HARMFUL_CONTENT = 'profile_harmful_content';

    public const REASON_PASSWORD_RESET_EMERGENCY_LOCK = 'password_reset_emergency_lock';

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
    public const PROFILE_REASONS = [
        self::PROFILE_REASON_IMPERSONATION,
        self::PROFILE_REASON_FAKE_OR_MISLEADING,
        self::PROFILE_REASON_INAPPROPRIATE_CONTENT,
        self::PROFILE_REASON_SPAM_ACCOUNT,
        self::PROFILE_REASON_HARMFUL_CONTENT,
    ];

    /**
     * @return array<string, string>
     */
    public static function profileReasonOptions(): array
    {
        return [
            self::PROFILE_REASON_IMPERSONATION => 'Impersonating another person or pet',
            self::PROFILE_REASON_FAKE_OR_MISLEADING => 'Fake or misleading profile',
            self::PROFILE_REASON_INAPPROPRIATE_CONTENT => 'Inappropriate profile content',
            self::PROFILE_REASON_SPAM_ACCOUNT => 'Spam account',
            self::PROFILE_REASON_HARMFUL_CONTENT => 'Harmful or dangerous content',
        ];
    }

    public static function profileReasonLabel(string $reason): string
    {
        return self::profileReasonOptions()[$reason] ?? str($reason)->replace('_', ' ')->title()->toString();
    }

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
