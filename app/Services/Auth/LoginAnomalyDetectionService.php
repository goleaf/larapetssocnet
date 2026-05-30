<?php

namespace App\Services\Auth;

use App\Models\Identity\User;
use App\Models\Security\AuthAuditLog;
use App\Models\Security\LoginSecurityAlert;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class LoginAnomalyDetectionService
{
    private const array HISTORY_EVENTS = [
        'login_success',
        'magic_link_accepted',
        'social_login_success',
        'two_factor_challenge_passed',
    ];

    public function __construct(
        private readonly GeoIpLookupService $geoIp,
        private readonly UserAgentDetailsService $userAgents,
        private readonly AuthMailDispatcher $mail,
    ) {}

    public function detectForRequest(User $user, Request $request, CarbonInterface $loginAt): void
    {
        $this->detect(
            (int) $user->getKey(),
            (string) $request->ip(),
            (string) $request->userAgent(),
            $loginAt,
        );
    }

    public function detect(int $userId, string $ipAddress, string $userAgent, CarbonInterface|string $loginAt): void
    {
        $user = User::query()
            ->select(['id', 'name', 'email', 'remember_token'])
            ->whereKey($userId)
            ->first();

        if (! $user instanceof User) {
            return;
        }

        $resolvedLoginAt = $loginAt instanceof CarbonInterface
            ? CarbonImmutable::instance($loginAt)
            : CarbonImmutable::parse($loginAt);
        $location = $this->geoIp->lookup($ipAddress);
        $countryCode = $location['country_code'];

        if (! is_string($countryCode) || in_array($countryCode, ['LOCAL', 'PRIVATE'], true)) {
            return;
        }

        if ($this->countrySeenRecently($user, $countryCode, $location['country'], $resolvedLoginAt)) {
            return;
        }

        if ($this->unresolvedAlertAlreadyExists($user, $countryCode)) {
            return;
        }

        $device = $this->userAgents->parse($userAgent);
        $plainToken = Str::random(64);

        $alert = LoginSecurityAlert::query()->create([
            'user_id' => $user->getKey(),
            'token_hash' => hash('sha256', $plainToken),
            'country_code' => $countryCode,
            'country' => $location['country'],
            'city' => $location['city'],
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'device_type' => $device['device_type'],
            'browser_name' => $device['browser_name'],
            'browser_version' => $device['browser_version'],
            'os_name' => $device['os_name'],
            'os_version' => $device['os_version'],
            'login_at' => $resolvedLoginAt,
        ]);

        $dismissUrl = URL::signedRoute('login-security-alert.dismiss', [
            'alert' => $alert->getKey(),
            'token' => $plainToken,
        ]);

        $secureUrl = URL::signedRoute('login-security-alert.secure', [
            'alert' => $alert->getKey(),
            'token' => $plainToken,
        ]);

        $this->mail->queueLoginAnomalySecurityAlert($user, $alert, $dismissUrl, $secureUrl);
    }

    private function countrySeenRecently(User $user, string $countryCode, string $country, CarbonImmutable $loginAt): bool
    {
        return AuthAuditLog::query()
            ->select(['country', 'additional_data'])
            ->where('user_id', $user->getKey())
            ->whereIn('event_type', self::HISTORY_EVENTS)
            ->where('created_at', '>=', $loginAt->subDays(90))
            ->where('created_at', '<', $loginAt)
            ->get()
            ->contains(function (AuthAuditLog $log) use ($countryCode, $country): bool {
                $loggedCountry = $log->getAttribute('country');
                $metadata = $log->getAttribute('additional_data');
                $loggedCountryCode = null;

                if (is_array($metadata)) {
                    $metadataCountryCode = $metadata['country_code'] ?? null;
                    $loggedCountryCode = is_string($metadataCountryCode) ? $metadataCountryCode : null;
                }

                return $loggedCountry === $country || $loggedCountryCode === $countryCode;
            });
    }

    private function unresolvedAlertAlreadyExists(User $user, string $countryCode): bool
    {
        return LoginSecurityAlert::query()
            ->where('user_id', $user->getKey())
            ->where('country_code', $countryCode)
            ->whereNull('dismissed_at')
            ->whereNull('secured_at')
            ->where('created_at', '>=', now()->subDay())
            ->exists();
    }
}
