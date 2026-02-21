<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Badge extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon',
        'condition_type',
        'condition_value',
    ];

    protected function casts(): array
    {
        return [
            'condition_value' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $badge): void {
            if (! $badge->slug && $badge->name) {
                $badge->slug = Str::slug((string) $badge->name);
            }
        });
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'badge_user')
            ->withPivot('awarded_at')
            ->withTimestamps();
    }
}
