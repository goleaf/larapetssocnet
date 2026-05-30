<?php

namespace App\Models\Content;

use Database\Factories\Content\CommentTranslationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[UseFactory(CommentTranslationFactory::class)]
#[Fillable([
    'comment_id',
    'source_language',
    'target_language',
    'translated_body',
    'provider',
    'cached_at',
])]
class CommentTranslation extends Model
{
    /** @use HasFactory<CommentTranslationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'cached_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Comment, $this>
     */
    public function comment(): BelongsTo
    {
        return $this->belongsTo(Comment::class);
    }
}
