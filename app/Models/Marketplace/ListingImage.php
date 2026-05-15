<?php

namespace App\Models\Marketplace;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable([
    'listing_id',
    'file_path',
    'order',
    'is_cover',
])]
class ListingImage extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'order' => 'integer',
            'is_cover' => 'boolean',
        ];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    protected function url(): Attribute
    {
        return Attribute::get(function (): string {
            $path = (string) ($this->file_path ?? '');

            if ($path === '') {
                return '';
            }

            if (Str::startsWith($path, ['http://', 'https://', '/'])) {
                return $path;
            }

            return asset('storage/'.ltrim($path, '/'));
        });
    }
}
