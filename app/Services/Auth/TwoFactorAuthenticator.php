<?php

namespace App\Services\Auth;

use App\Models\Identity\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;

class TwoFactorAuthenticator
{
    private const string ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    private const int PERIOD_SECONDS = 30;

    private const int SECRET_BYTES = 20;

    public function generateSecret(): string
    {
        return $this->base32Encode(random_bytes(self::SECRET_BYTES));
    }

    public function codeAt(string $secret, ?int $timestamp = null): string
    {
        $counter = intdiv($timestamp ?? time(), self::PERIOD_SECONDS);
        $key = $this->base32Decode($secret);
        $binaryCounter = pack('N*', 0).pack('N*', $counter);
        $hash = hash_hmac('sha1', $binaryCounter, $key, true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $truncated = unpack('N', substr($hash, $offset, 4))[1] & 0x7FFFFFFF;

        return str_pad((string) ($truncated % 1_000_000), 6, '0', STR_PAD_LEFT);
    }

    public function verifyCode(string $secret, string $code, int $window = 1): bool
    {
        $normalized = preg_replace('/\s+/', '', $code);

        if (! is_string($normalized) || ! preg_match('/^\d{6}$/', $normalized)) {
            return false;
        }

        $timestamp = time();

        for ($step = -$window; $step <= $window; $step++) {
            $candidate = $this->codeAt($secret, $timestamp + ($step * self::PERIOD_SECONDS));

            if (hash_equals($candidate, $normalized)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public function generateRecoveryCodes(int $count = 8): array
    {
        return collect(range(1, $count))
            ->map(fn (): string => Str::password(10, letters: true, numbers: true, symbols: false, spaces: false))
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $codes
     * @return list<string>
     */
    public function hashRecoveryCodes(array $codes): array
    {
        return collect($codes)
            ->map(fn (string $code): string => Hash::make($this->normalizeRecoveryCode($code)))
            ->values()
            ->all();
    }

    public function consumeRecoveryCode(User $user, string $code): bool
    {
        $normalized = $this->normalizeRecoveryCode($code);
        $hashes = collect($user->two_factor_recovery_codes ?? []);
        $matchedHash = $hashes->first(fn (string $hash): bool => Hash::check($normalized, $hash));

        if (! is_string($matchedHash)) {
            return false;
        }

        $user->forceFill([
            'two_factor_recovery_codes' => $hashes
                ->reject(fn (string $hash): bool => hash_equals($hash, $matchedHash))
                ->values()
                ->all(),
        ])->save();

        return true;
    }

    public function otpauthUri(User $user, string $secret): string
    {
        $issuer = rawurlencode((string) config('app.name', 'PetSocial'));
        $label = rawurlencode(config('app.name', 'PetSocial').':'.$user->email);

        return "otpauth://totp/{$label}?secret={$secret}&issuer={$issuer}&algorithm=SHA1&digits=6&period=".self::PERIOD_SECONDS;
    }

    public function qrSvgPayload(string $uri): string
    {
        $hash = hash('sha256', $uri);
        $cells = '';

        for ($row = 0; $row < 21; $row++) {
            for ($col = 0; $col < 21; $col++) {
                $bit = hexdec($hash[($row * 21 + $col) % strlen($hash)]) % 2 === 0;

                if ($this->isFinderCell($row, $col) || $bit) {
                    $cells .= sprintf('<rect x="%d" y="%d" width="1" height="1"/>', $col, $row);
                }
            }
        }

        return '<svg data-ui="two-factor-qr-code" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 21 21" role="img" aria-label="Two-factor setup QR code"><rect width="21" height="21" fill="#fff8ec"/><g fill="#34241c">'.$cells.'</g></svg>';
    }

    private function normalizeRecoveryCode(string $code): string
    {
        return Str::lower(str_replace([' ', '-'], '', trim($code)));
    }

    private function base32Encode(string $bytes): string
    {
        $bits = '';

        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        return collect(str_split($bits, 5))
            ->map(function (string $chunk): string {
                $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);

                return self::ALPHABET[bindec($chunk)];
            })
            ->implode('');
    }

    private function base32Decode(string $secret): string
    {
        $normalized = strtoupper(preg_replace('/[^A-Z2-7]/', '', $secret) ?? '');
        $bits = '';

        foreach (str_split($normalized) as $character) {
            $position = strpos(self::ALPHABET, $character);

            if ($position === false) {
                throw new InvalidArgumentException('Invalid TOTP secret.');
            }

            $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
        }

        $bytes = '';

        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $bytes .= chr(bindec($byte));
            }
        }

        return $bytes;
    }

    private function isFinderCell(int $row, int $col): bool
    {
        foreach ([[0, 0], [0, 14], [14, 0]] as [$startRow, $startCol]) {
            $inRow = $row >= $startRow && $row <= $startRow + 6;
            $inCol = $col >= $startCol && $col <= $startCol + 6;

            if (! $inRow || ! $inCol) {
                continue;
            }

            return $row === $startRow
                || $row === $startRow + 6
                || $col === $startCol
                || $col === $startCol + 6
                || ($row >= $startRow + 2 && $row <= $startRow + 4 && $col >= $startCol + 2 && $col <= $startCol + 4);
        }

        return false;
    }
}
