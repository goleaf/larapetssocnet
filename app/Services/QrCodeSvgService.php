<?php

namespace App\Services;

use InvalidArgumentException;

class QrCodeSvgService
{
    /**
     * @var array<int, array{size:int,data:int,ecc:int,alignment:list<int>}>
     */
    private const VERSIONS = [
        1 => ['size' => 21, 'data' => 19, 'ecc' => 7, 'alignment' => []],
        2 => ['size' => 25, 'data' => 34, 'ecc' => 10, 'alignment' => [6, 18]],
        3 => ['size' => 29, 'data' => 55, 'ecc' => 15, 'alignment' => [6, 22]],
        4 => ['size' => 33, 'data' => 80, 'ecc' => 20, 'alignment' => [6, 26]],
        5 => ['size' => 37, 'data' => 108, 'ecc' => 26, 'alignment' => [6, 30]],
    ];

    public function svg(string $payload, int $scale = 6, int $quietZone = 4): string
    {
        $bytes = array_values(unpack('C*', $payload) ?: []);
        $version = $this->versionForBytes(count($bytes));
        $spec = self::VERSIONS[$version];
        $data = $this->encodeData($bytes, $spec['data']);
        $codewords = array_merge($data, $this->reedSolomon($data, $spec['ecc']));
        $matrix = $this->matrix($version, $codewords);
        $size = $spec['size'];
        $viewBox = ($size + ($quietZone * 2)) * $scale;
        $rects = [];

        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                if ($matrix[$y][$x] !== true) {
                    continue;
                }

                $rects[] = sprintf(
                    '<rect x="%d" y="%d" width="%d" height="%d"/>',
                    ($x + $quietZone) * $scale,
                    ($y + $quietZone) * $scale,
                    $scale,
                    $scale
                );
            }
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Pet profile QR code" viewBox="0 0 '.$viewBox.' '.$viewBox.'" width="180" height="180">'
            .'<rect width="100%" height="100%" fill="#fff"/>'
            .'<g fill="#271f1b">'.implode('', $rects).'</g>'
            .'</svg>';
    }

    private function versionForBytes(int $byteCount): int
    {
        foreach (self::VERSIONS as $version => $spec) {
            $bits = 4 + 8 + ($byteCount * 8);

            if ($bits <= $spec['data'] * 8) {
                return $version;
            }
        }

        throw new InvalidArgumentException('QR payload is too long for the built-in pet profile encoder.');
    }

    /**
     * @param  list<int>  $bytes
     * @return list<int>
     */
    private function encodeData(array $bytes, int $dataCodewords): array
    {
        $bits = $this->bits(4, 4);
        $bits = array_merge($bits, $this->bits(count($bytes), 8));

        foreach ($bytes as $byte) {
            $bits = array_merge($bits, $this->bits($byte, 8));
        }

        $capacity = $dataCodewords * 8;
        $bits = array_pad($bits, min($capacity, count($bits) + 4), 0);

        while (count($bits) % 8 !== 0) {
            $bits[] = 0;
        }

        $codewords = [];
        foreach (array_chunk($bits, 8) as $chunk) {
            $codewords[] = bindec(implode('', $chunk));
        }

        $pad = [0xEC, 0x11];
        $index = 0;

        while (count($codewords) < $dataCodewords) {
            $codewords[] = $pad[$index % 2];
            $index++;
        }

        return $codewords;
    }

    /**
     * @param  list<int>  $codewords
     * @return array<int, array<int, bool|null>>
     */
    private function matrix(int $version, array $codewords): array
    {
        $size = self::VERSIONS[$version]['size'];
        $matrix = array_fill(0, $size, array_fill(0, $size, null));
        $reserved = array_fill(0, $size, array_fill(0, $size, false));

        $this->finder($matrix, $reserved, 0, 0);
        $this->finder($matrix, $reserved, $size - 7, 0);
        $this->finder($matrix, $reserved, 0, $size - 7);
        $this->timing($matrix, $reserved);
        $this->alignment($matrix, $reserved, self::VERSIONS[$version]['alignment']);
        $this->set($matrix, $reserved, 8, $size - 8, true);
        $this->reserveFormat($reserved, $size);

        $bits = [];
        foreach ($codewords as $codeword) {
            $bits = array_merge($bits, $this->bits($codeword, 8));
        }

        $this->placeData($matrix, $reserved, $bits);
        $this->applyMask($matrix, $reserved);
        $this->format($matrix, $reserved, 0);

        return $matrix;
    }

    /**
     * @param  array<int, array<int, bool|null>>  $matrix
     * @param  array<int, array<int, bool>>  $reserved
     */
    private function finder(array &$matrix, array &$reserved, int $left, int $top): void
    {
        for ($y = -1; $y <= 7; $y++) {
            for ($x = -1; $x <= 7; $x++) {
                $xx = $left + $x;
                $yy = $top + $y;

                if (! isset($matrix[$yy][$xx])) {
                    continue;
                }

                $isFinder = $x >= 0 && $x <= 6 && $y >= 0 && $y <= 6
                    && ($x === 0 || $x === 6 || $y === 0 || $y === 6 || ($x >= 2 && $x <= 4 && $y >= 2 && $y <= 4));

                $this->set($matrix, $reserved, $xx, $yy, $isFinder);
            }
        }
    }

    /**
     * @param  array<int, array<int, bool|null>>  $matrix
     * @param  array<int, array<int, bool>>  $reserved
     */
    private function timing(array &$matrix, array &$reserved): void
    {
        $size = count($matrix);

        for ($i = 8; $i < $size - 8; $i++) {
            $value = $i % 2 === 0;
            $this->set($matrix, $reserved, 6, $i, $value);
            $this->set($matrix, $reserved, $i, 6, $value);
        }
    }

    /**
     * @param  array<int, array<int, bool|null>>  $matrix
     * @param  array<int, array<int, bool>>  $reserved
     * @param  list<int>  $positions
     */
    private function alignment(array &$matrix, array &$reserved, array $positions): void
    {
        foreach ($positions as $cx) {
            foreach ($positions as $cy) {
                if ($reserved[$cy][$cx] ?? false) {
                    continue;
                }

                for ($y = -2; $y <= 2; $y++) {
                    for ($x = -2; $x <= 2; $x++) {
                        $value = max(abs($x), abs($y)) !== 1;
                        $this->set($matrix, $reserved, $cx + $x, $cy + $y, $value);
                    }
                }
            }
        }
    }

    /**
     * @param  array<int, array<int, bool|null>>  $matrix
     * @param  array<int, array<int, bool>>  $reserved
     */
    private function set(array &$matrix, array &$reserved, int $x, int $y, bool $value): void
    {
        if (! isset($matrix[$y][$x])) {
            return;
        }

        $matrix[$y][$x] = $value;
        $reserved[$y][$x] = true;
    }

    /**
     * @param  array<int, array<int, bool>>  $reserved
     */
    private function reserveFormat(array &$reserved, int $size): void
    {
        for ($i = 0; $i < 9; $i++) {
            if ($i !== 6) {
                $reserved[8][$i] = true;
                $reserved[$i][8] = true;
            }
        }

        for ($i = 0; $i < 8; $i++) {
            $reserved[8][$size - 1 - $i] = true;
            $reserved[$size - 1 - $i][8] = true;
        }
    }

    /**
     * @param  array<int, array<int, bool|null>>  $matrix
     * @param  array<int, array<int, bool>>  $reserved
     * @param  list<int>  $bits
     */
    private function placeData(array &$matrix, array $reserved, array $bits): void
    {
        $size = count($matrix);
        $bit = 0;
        $direction = -1;

        for ($x = $size - 1; $x > 0; $x -= 2) {
            if ($x === 6) {
                $x--;
            }

            for ($step = 0; $step < $size; $step++) {
                $y = $direction === -1 ? $size - 1 - $step : $step;

                for ($dx = 0; $dx < 2; $dx++) {
                    $xx = $x - $dx;

                    if ($reserved[$y][$xx]) {
                        continue;
                    }

                    $matrix[$y][$xx] = (($bits[$bit] ?? 0) === 1);
                    $bit++;
                }
            }

            $direction *= -1;
        }
    }

    /**
     * @param  array<int, array<int, bool|null>>  $matrix
     * @param  array<int, array<int, bool>>  $reserved
     */
    private function applyMask(array &$matrix, array $reserved): void
    {
        $size = count($matrix);

        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                if ($reserved[$y][$x] || (($x + $y) % 2 !== 0)) {
                    continue;
                }

                $matrix[$y][$x] = ! (bool) $matrix[$y][$x];
            }
        }
    }

    /**
     * @param  array<int, array<int, bool|null>>  $matrix
     * @param  array<int, array<int, bool>>  $reserved
     */
    private function format(array &$matrix, array &$reserved, int $mask): void
    {
        $size = count($matrix);
        $format = $this->formatBits((1 << 3) | $mask);
        $bits = $this->bits($format, 15);

        for ($i = 0; $i <= 5; $i++) {
            $this->set($matrix, $reserved, 8, $i, (bool) $bits[$i]);
        }

        $this->set($matrix, $reserved, 8, 7, (bool) $bits[6]);
        $this->set($matrix, $reserved, 8, 8, (bool) $bits[7]);
        $this->set($matrix, $reserved, 7, 8, (bool) $bits[8]);

        for ($i = 9; $i < 15; $i++) {
            $this->set($matrix, $reserved, 14 - $i, 8, (bool) $bits[$i]);
        }

        for ($i = 0; $i < 8; $i++) {
            $this->set($matrix, $reserved, $size - 1 - $i, 8, (bool) $bits[$i]);
        }

        for ($i = 8; $i < 15; $i++) {
            $this->set($matrix, $reserved, 8, $size - 15 + $i, (bool) $bits[$i]);
        }
    }

    private function formatBits(int $data): int
    {
        $value = $data << 10;
        $generator = 0x537;

        for ($i = 14; $i >= 10; $i--) {
            if ((($value >> $i) & 1) === 1) {
                $value ^= $generator << ($i - 10);
            }
        }

        return (($data << 10) | $value) ^ 0x5412;
    }

    /**
     * @param  list<int>  $data
     * @return list<int>
     */
    private function reedSolomon(array $data, int $eccLength): array
    {
        [$exp, $log] = $this->galoisTables();
        $generator = [1];

        for ($i = 0; $i < $eccLength; $i++) {
            $generator = $this->polyMultiply($generator, [1, $exp[$i]], $exp, $log);
        }

        $result = array_fill(0, $eccLength, 0);

        foreach ($data as $byte) {
            $factor = $byte ^ $result[0];
            array_shift($result);
            $result[] = 0;

            for ($i = 0; $i < $eccLength; $i++) {
                $result[$i] ^= $this->gfMultiply($generator[$i + 1], $factor, $exp, $log);
            }
        }

        return $result;
    }

    /**
     * @param  list<int>  $a
     * @param  list<int>  $b
     * @param  list<int>  $exp
     * @param  list<int>  $log
     * @return list<int>
     */
    private function polyMultiply(array $a, array $b, array $exp, array $log): array
    {
        $result = array_fill(0, count($a) + count($b) - 1, 0);

        foreach ($a as $i => $av) {
            foreach ($b as $j => $bv) {
                $result[$i + $j] ^= $this->gfMultiply($av, $bv, $exp, $log);
            }
        }

        return $result;
    }

    /**
     * @return array{0:list<int>,1:list<int>}
     */
    private function galoisTables(): array
    {
        $exp = array_fill(0, 512, 0);
        $log = array_fill(0, 256, 0);
        $value = 1;

        for ($i = 0; $i < 255; $i++) {
            $exp[$i] = $value;
            $log[$value] = $i;
            $value <<= 1;

            if (($value & 0x100) !== 0) {
                $value ^= 0x11D;
            }
        }

        for ($i = 255; $i < 512; $i++) {
            $exp[$i] = $exp[$i - 255];
        }

        return [$exp, $log];
    }

    /**
     * @param  list<int>  $exp
     * @param  list<int>  $log
     */
    private function gfMultiply(int $a, int $b, array $exp, array $log): int
    {
        if ($a === 0 || $b === 0) {
            return 0;
        }

        return $exp[$log[$a] + $log[$b]];
    }

    /**
     * @return list<int>
     */
    private function bits(int $value, int $length): array
    {
        $bits = [];

        for ($i = $length - 1; $i >= 0; $i--) {
            $bits[] = ($value >> $i) & 1;
        }

        return $bits;
    }
}
