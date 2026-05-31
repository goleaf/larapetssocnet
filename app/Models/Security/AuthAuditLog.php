<?php

namespace App\Models\Security;

use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'event_type',
    'ip_address',
    'user_agent',
    'country',
    'city',
    'additional_data',
])]
class AuthAuditLog extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'additional_data' => 'array',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getMetadataAttribute(mixed $value): ?array
    {
        if (is_array($value)) {
            return $this->normalizeMetadata($value);
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $this->normalizeMetadata($decoded) : null;
        }

        return $this->additionalData();
    }

    public function getIdentifierHashAttribute(mixed $value): ?string
    {
        return is_string($value) ? $value : $this->identifierHashValue();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function additionalData(): ?array
    {
        $metadata = $this->getAttribute('additional_data');

        if (! is_array($metadata)) {
            return null;
        }

        return $this->normalizeMetadata($metadata);
    }

    private function identifierHashValue(): ?string
    {
        $metadata = $this->additionalData();
        $identifierHash = $metadata['identifier_hash'] ?? null;

        return is_string($identifierHash) ? $identifierHash : null;
    }

    /**
     * @param  array<mixed, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function normalizeMetadata(array $metadata): array
    {
        $normalized = [];

        foreach ($metadata as $key => $value) {
            $normalized[(string) $key] = $value;
        }

        return $normalized;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
