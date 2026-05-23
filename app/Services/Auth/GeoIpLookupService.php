<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\File;
use Throwable;

class GeoIpLookupService
{
    /**
     * @var list<array{cidr: string, country_code: string|null, country: string|null, city: string|null}>|null
     */
    private ?array $ranges = null;

    /**
     * @return array{country_code: string|null, country: string, city: string|null, label: string}
     */
    public function lookup(?string $ipAddress): array
    {
        $ipAddress = trim((string) $ipAddress);

        if ($ipAddress === '' || filter_var($ipAddress, FILTER_VALIDATE_IP) === false) {
            return $this->unknownLocation();
        }

        foreach ($this->ranges() as $range) {
            if (! $this->ipMatchesCidr($ipAddress, $range['cidr'])) {
                continue;
            }

            $country = $range['country'] ?: 'Unknown location';
            $city = $range['city'];

            return [
                'country_code' => $range['country_code'],
                'country' => $country,
                'city' => $city,
                'label' => $city ? $city.', '.$country : $country,
            ];
        }

        return $this->unknownLocation();
    }

    /**
     * @return list<array{cidr: string, country_code: string|null, country: string|null, city: string|null}>
     */
    private function ranges(): array
    {
        if ($this->ranges !== null) {
            return $this->ranges;
        }

        $path = (string) config('geoip.database_path', database_path('geoip/ip-ranges.json'));

        if (! File::isFile($path)) {
            return $this->ranges = [];
        }

        try {
            $decoded = json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable $throwable) {
            report($throwable);

            return $this->ranges = [];
        }

        if (! is_array($decoded)) {
            return $this->ranges = [];
        }

        $ranges = [];

        foreach ($decoded as $range) {
            if (! is_array($range) || ! is_string($range['cidr'] ?? null)) {
                continue;
            }

            $ranges[] = [
                'cidr' => $range['cidr'],
                'country_code' => is_string($range['country_code'] ?? null) ? $range['country_code'] : null,
                'country' => is_string($range['country'] ?? null) ? $range['country'] : null,
                'city' => is_string($range['city'] ?? null) ? $range['city'] : null,
            ];
        }

        return $this->ranges = $ranges;
    }

    private function ipMatchesCidr(string $ipAddress, string $cidr): bool
    {
        [$subnet, $bits] = array_pad(explode('/', $cidr, 2), 2, null);

        if (! is_string($bits) || ! is_numeric($bits)) {
            return hash_equals($ipAddress, $subnet);
        }

        $ipBytes = inet_pton($ipAddress);
        $subnetBytes = inet_pton($subnet);

        if ($ipBytes === false || $subnetBytes === false || strlen($ipBytes) !== strlen($subnetBytes)) {
            return false;
        }

        $bitCount = (int) $bits;
        $byteCount = intdiv($bitCount, 8);
        $remainingBits = $bitCount % 8;

        if ($byteCount > 0 && substr($ipBytes, 0, $byteCount) !== substr($subnetBytes, 0, $byteCount)) {
            return false;
        }

        if ($remainingBits === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remainingBits)) & 0xFF;

        return (ord($ipBytes[$byteCount]) & $mask) === (ord($subnetBytes[$byteCount]) & $mask);
    }

    /**
     * @return array{country_code: string|null, country: string, city: string|null, label: string}
     */
    private function unknownLocation(): array
    {
        return [
            'country_code' => null,
            'country' => 'Unknown location',
            'city' => null,
            'label' => 'Unknown location',
        ];
    }
}
