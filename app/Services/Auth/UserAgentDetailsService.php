<?php

namespace App\Services\Auth;

use DeviceDetector\DeviceDetector;

class UserAgentDetailsService
{
    /**
     * @return array{device_type: string, browser_name: string, browser_version: string|null, os_name: string, os_version: string|null, browser_label: string, os_label: string, summary: string}
     */
    public function parse(?string $userAgent): array
    {
        $userAgent = trim((string) $userAgent);

        if ($userAgent === '') {
            return $this->unknownDetails();
        }

        $detector = new DeviceDetector($userAgent);
        $detector->parse();

        $client = $detector->getClient();
        $os = $detector->getOs();

        $browserName = $this->stringValue($client, 'name', 'Unknown browser');
        $browserVersion = $this->nullableStringValue($client, 'version');
        $osName = $this->stringValue($os, 'name', 'Unknown OS');
        $osVersion = $this->nullableStringValue($os, 'version');
        $deviceType = $this->normalizeDeviceType($detector->getDeviceName());

        $browserLabel = $this->label($browserName, $browserVersion);
        $osLabel = $this->label($osName, $osVersion);

        return [
            'device_type' => $deviceType,
            'browser_name' => $browserName,
            'browser_version' => $browserVersion,
            'os_name' => $osName,
            'os_version' => $osVersion,
            'browser_label' => $browserLabel,
            'os_label' => $osLabel,
            'summary' => $browserLabel.' on '.$osLabel,
        ];
    }

    /**
     * @return array{device_type: string, browser_name: string, browser_version: string|null, os_name: string, os_version: string|null, browser_label: string, os_label: string, summary: string}
     */
    private function unknownDetails(): array
    {
        return [
            'device_type' => 'desktop',
            'browser_name' => 'Unknown browser',
            'browser_version' => null,
            'os_name' => 'Unknown OS',
            'os_version' => null,
            'browser_label' => 'Unknown browser',
            'os_label' => 'Unknown OS',
            'summary' => 'Unknown browser on Unknown OS',
        ];
    }

    /**
     * @param  array<string, mixed>|string|null  $source
     */
    private function stringValue(array|string|null $source, string $key, string $fallback): string
    {
        if (! is_array($source)) {
            return $fallback;
        }

        $value = $source[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : $fallback;
    }

    /**
     * @param  array<string, mixed>|string|null  $source
     */
    private function nullableStringValue(array|string|null $source, string $key): ?string
    {
        if (! is_array($source)) {
            return null;
        }

        $value = $source[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function normalizeDeviceType(string $deviceName): string
    {
        return match ($deviceName) {
            'smartphone', 'feature phone', 'phablet' => 'mobile',
            'tablet' => 'tablet',
            default => 'desktop',
        };
    }

    private function label(string $name, ?string $version): string
    {
        if ($version === null || $version === '') {
            return $name;
        }

        return $name.' '.$version;
    }
}
