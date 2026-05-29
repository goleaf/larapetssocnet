<?php

namespace App\Models\Content;

use App\Models\Identity\User;
use Database\Factories\Content\PostDraftFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[UseFactory(PostDraftFactory::class)]
#[Fillable([
    'user_id',
    'context_type',
    'context_id',
    'body',
    'visibility',
    'mood',
    'location',
    'location_lat',
    'location_lng',
    'tagged_pets',
    'media_payload',
    'link_preview',
    'state',
    'state_hash',
    'scheduled_publish_at',
    'last_autosaved_at',
])]
class PostDraft extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'context_id' => 'integer',
            'location_lat' => 'decimal:7',
            'location_lng' => 'decimal:7',
            'tagged_pets' => 'array',
            'media_payload' => 'array',
            'link_preview' => 'array',
            'state' => 'array',
            'state_hash' => 'string',
            'scheduled_publish_at' => 'datetime',
            'last_autosaved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
