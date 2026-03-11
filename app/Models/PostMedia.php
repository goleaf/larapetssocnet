<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class PostMedia extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'post_media';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'post_id',
        'file_path',
        'media_type',
        'order',
    ];

    protected function casts(): array
    {
        return [
            'order' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->file_path);
    }
}
