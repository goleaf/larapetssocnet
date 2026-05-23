<?php

namespace App\Services\Auth;

use App\Models\Identity\User;
use App\Models\Security\AuthAuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

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

        $payload = [
            'user_id' => $user?->getKey(),
            'event_type' => $eventType,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'metadata' => $metadata === [] ? null : $metadata,
        ];

        if (Schema::hasColumn('auth_audit_logs', 'identifier_hash')) {
            $identifierHash = $metadata['identifier_hash'] ?? null;
            $payload['identifier_hash'] = is_string($identifierHash) ? $identifierHash : null;
        }

        AuthAuditLog::query()->create($payload);
    }
}
