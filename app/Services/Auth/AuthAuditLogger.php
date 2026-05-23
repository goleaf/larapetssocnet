<?php

namespace App\Services\Auth;

use App\Models\Identity\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class AuthAuditLogger
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function record(?User $user, string $eventType, ?Request $request = null, array $metadata = []): void
    {
        if (! Schema::hasTable('auth_audit_logs')) {
            return;
        }

        try {
            $columns = array_flip(Schema::getColumnListing('auth_audit_logs'));

            if (! isset($columns['event_type'], $columns['ip_address'], $columns['user_agent'])) {
                return;
            }

            $payload = [
                'event_type' => $eventType,
                'ip_address' => $request?->ip() ?? '',
                'user_agent' => $request?->userAgent() ?? '',
            ];

            if (isset($columns['user_id'])) {
                $payload['user_id'] = $user?->getKey();
            }

            if (isset($columns['country'])) {
                $payload['country'] = null;
            }

            if (isset($columns['city'])) {
                $payload['city'] = null;
            }

            $encodedMetadata = $metadata === [] ? null : json_encode($metadata, JSON_THROW_ON_ERROR);

            if (isset($columns['additional_data'])) {
                $payload['additional_data'] = $encodedMetadata;
            } elseif (isset($columns['metadata'])) {
                $payload['metadata'] = $encodedMetadata;
            }

            if (isset($columns['identifier_hash'])) {
                $identifierHash = $metadata['identifier_hash'] ?? null;
                $payload['identifier_hash'] = is_string($identifierHash) ? $identifierHash : null;
            }

            if (isset($columns['created_at'])) {
                $payload['created_at'] = now();
            }

            if (isset($columns['updated_at'])) {
                $payload['updated_at'] = now();
            }

            DB::table('auth_audit_logs')->insert($payload);
        } catch (Throwable $throwable) {
            report($throwable);
        }
    }
}
