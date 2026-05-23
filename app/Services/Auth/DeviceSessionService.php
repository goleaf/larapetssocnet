<?php

namespace App\Services\Auth;

use App\Models\Identity\User;
use Illuminate\Support\Facades\DB;

class DeviceSessionService
{
    public function __construct(
        private readonly UserAgentDetailsService $userAgents,
        private readonly GeoIpLookupService $geoIp,
    ) {}

    /**
     * @return list<array{id: string, ip_address: string|null, user_agent: string|null, device_type: string, browser_name: string, browser_version: string|null, os_name: string, os_version: string|null, browser_label: string, os_label: string, summary: string, country_code: string|null, country: string, city: string|null, location_label: string, is_current: bool, last_activity: int}>
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
     * @return array{id: string, ip_address: string|null, user_agent: string|null, device_type: string, browser_name: string, browser_version: string|null, os_name: string, os_version: string|null, browser_label: string, os_label: string, summary: string, country_code: string|null, country: string, city: string|null, location_label: string, is_current: bool, last_activity: int}
     */
    private function sessionPayload(object $session, ?string $currentSessionId): array
    {
        $userAgent = is_string($session->user_agent) ? $session->user_agent : null;
        $ipAddress = is_string($session->ip_address) ? $session->ip_address : null;
        $device = $this->userAgents->parse($userAgent);
        $location = $this->geoIp->lookup($ipAddress);

        return [
            'id' => (string) $session->id,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'device_type' => $device['device_type'],
            'browser_name' => $device['browser_name'],
            'browser_version' => $device['browser_version'],
            'os_name' => $device['os_name'],
            'os_version' => $device['os_version'],
            'browser_label' => $device['browser_label'],
            'os_label' => $device['os_label'],
            'summary' => $device['summary'],
            'country_code' => $location['country_code'],
            'country' => $location['country'],
            'city' => $location['city'],
            'location_label' => $location['label'],
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

    public function destroySession(User $user, string $sessionId, string $currentSessionId): int
    {
        if (hash_equals($sessionId, $currentSessionId)) {
            return 0;
        }

        return DB::table('sessions')
            ->where('user_id', $user->getKey())
            ->where('id', $sessionId)
            ->delete();
    }

    public function destroyAllSessions(User $user): int
    {
        return DB::table('sessions')
            ->where('user_id', $user->getKey())
            ->delete();
    }
}
