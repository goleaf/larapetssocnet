<?php

namespace App\Models\Analytics;

use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'profile_user_id',
    'viewer_user_id',
    'viewed_on',
])]
class ProfileView extends Model
{
    protected function casts(): array
    {
        return [
            'viewed_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function profileUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'profile_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function viewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'viewer_user_id');
    }
}
