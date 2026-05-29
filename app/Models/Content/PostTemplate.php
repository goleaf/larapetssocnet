<?php

namespace App\Models\Content;

use App\Models\Identity\User;
use Database\Factories\Content\PostTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[UseFactory(PostTemplateFactory::class)]
#[Fillable([
    'user_id',
    'name',
    'template_text',
])]
class PostTemplate extends Model
{
    /** @use HasFactory<PostTemplateFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
