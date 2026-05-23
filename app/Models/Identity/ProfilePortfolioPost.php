<?php

namespace App\Models\Identity;

use App\Models\Content\Post;
use Database\Factories\Identity\ProfilePortfolioPostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'post_id',
    'display_order',
])]
#[Table(name: 'profile_portfolio_posts')]
class ProfilePortfolioPost extends Model
{
    /** @use HasFactory<ProfilePortfolioPostFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Post, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
