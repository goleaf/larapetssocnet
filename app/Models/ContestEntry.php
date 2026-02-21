<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class ContestEntry extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'contest_id',
        'user_id',
        'pet_id',
        'post_id',
        'caption',
        'votes_count',
    ];

    protected function casts(): array
    {
        return [
            'votes_count' => 'integer',
        ];
    }

    public function contest(): BelongsTo
    {
        return $this->belongsTo(Contest::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(ContestVote::class, 'entry_id');
    }
}
