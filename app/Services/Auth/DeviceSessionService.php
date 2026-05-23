<?php

namespace App\Services\Auth;

use App\Models\Identity\User;
use Illuminate\Support\Facades\DB;

class DeviceSessionService
{
    /**
     * @return list<array{id: string, ip_address: string|null, user_agent: string|null, browser: string, platform: string, is_current: bool, last_activity: int}>
     */
    public function activeSessions(User $user, ?string $currentSessionId = null): array
    {
        return DB::table('sessions')
            ->select(['id', 'ip_address', 'user_agent', 'last_activity'])
            ->where('user_id', $user->getKey())
            ->orderByDesc('last_activity')
            ->get()
            ->map(function (object $session) use ($currentSessionId): array {
                return $this->sessionPayload($session, $currentSessionId);
            })
            ->values()
            ->all();
    }

    /**
     * @return array{id: string, ip_address: string|null, user_agent: string|null, browser: string, platform: string, is_current: bool, last_activity: int}
     */
    private function sessionPayload(object $session, ?string $currentSessionId): array
    {
        $userAgent = is_string($session->user_agent) ? $session->user_agent : null;

        return [
            'id' => (string) $session->id,
            'ip_address' => is_string($session->ip_address) ? $session->ip_address : null,
            'user_agent' => $userAgent,
            'browser' => $this->browser($userAgent),
            'platform' => $this->platform($userAgent),
            'is_current' => $currentSessionId !== null && hash_equals((string) $session->id, $currentSessionId),
            'last_activity' => (int) $session->last_activity,
        ];
    }

    public function destroyOtherSessions(User $user, string $currentSessionId): int
    {
        return DB::table('sessions')
            ->where('user_id', $user->getKey())
            ->where('id', '!=', $currentSessionId)
            ->delete();
    }

    public function destroyAllSessions(User $user): int
    {
        return DB::table('sessions')
            ->where('user_id', $user->getKey())
            ->delete();
    }

    private function browser(?string $userAgent): string
    {
        return match (true) {
            $userAgent === null || $userAgent === '' => 'Unknown browser',
            str_contains($userAgent, 'Edg/') => 'Microsoft Edge',
            str_contains($userAgent, 'Chrome/') => 'Chrome',
            str_contains($userAgent, 'Safari/') && str_contains($userAgent, 'Version/') => 'Safari',
            str_contains($userAgent, 'Firefox/') => 'Firefox',
            default => 'Unknown browser',
        };
    }

    private function platform(?string $userAgent): string
    {
        return match (true) {
            $userAgent === null || $userAgent === '' => 'Unknown device',
            str_contains($userAgent, 'iPhone') => 'iPhone',
            str_contains($userAgent, 'iPad') => 'iPad',
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'Mac OS X') => 'macOS',
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => 'Unknown device',
        };
    }
}
