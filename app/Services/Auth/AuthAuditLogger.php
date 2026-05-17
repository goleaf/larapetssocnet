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

        AuthAuditLog::query()->create([
            'user_id' => $user?->getKey(),
            'event_type' => $eventType,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'metadata' => $metadata === [] ? null : $metadata,
        ]);
    }
}
