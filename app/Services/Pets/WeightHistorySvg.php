<?php

namespace App\Services\Pets;

use Carbon\CarbonImmutable;
use DateTimeInterface;

class WeightHistorySvg
{
    /**
     * @param  iterable<int, array{date: string|DateTimeInterface, weight: int|float|string}>  $entries
     */
    public function render(iterable $entries, string $unit = 'kg'): ?string
    {
        $points = $this->normalizePoints($entries);

        if ($points === []) {
            return null;
        }

        $unit = in_array($unit, ['kg', 'lbs'], true) ? $unit : 'kg';
        $width = 600;
        $height = 200;
        $left = 48;
        $right = 24;
        $top = 18;
        $bottom = 34;
        $chartWidth = $width - $left - $right;
        $chartHeight = $height - $top - $bottom;
        $minTime = min(array_column($points, 'time'));
        $maxTime = max(array_column($points, 'time'));
        $minWeight = min(array_column($points, 'weight'));
        $maxWeight = max(array_column($points, 'weight'));

        if ($minWeight === $maxWeight) {
            $minWeight = max(0, $minWeight - 1);
            $maxWeight++;
        }

        $coordinates = array_map(
            fn (array $point): array => [
                'x' => $this->scale($point['time'], $minTime, $maxTime, $left, $left + $chartWidth),
                'y' => $this->scale($point['weight'], $minWeight, $maxWeight, $top + $chartHeight, $top),
                'weight' => $point['weight'],
                'date' => $point['date'],
            ],
            $points
        );

        $line = count($coordinates) === 1 ? null : $this->bezierPath($coordinates);
        $area = $line
            ? $line.' L '.$coordinates[array_key_last($coordinates)]['x'].' '.($top + $chartHeight).' L '.$coordinates[0]['x'].' '.($top + $chartHeight).' Z'
            : null;
        $dots = collect($coordinates)
            ->map(fn (array $point): string => sprintf(
                '<circle cx="%.2f" cy="%.2f" r="4"><title>%s %s on %s</title></circle>',
                $point['x'],
                $point['y'],
                $this->formatWeight($point['weight']),
                $unit,
                e($point['date'])
            ))
            ->join('');
        $latest = $coordinates[array_key_last($coordinates)];
        $firstYear = CarbonImmutable::parse($points[0]['date'])->format('Y');
        $lastYear = CarbonImmutable::parse($points[array_key_last($points)]['date'])->format('Y');

        return '<svg xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Pet weight history chart" viewBox="0 0 600 200" width="100%" height="200">'
            .'<rect width="600" height="200" rx="12" fill="var(--surface-subtle, #fffaf3)"/>'
            .'<g stroke="rgba(95,74,61,.18)" stroke-width="1">'
            .'<line x1="'.$left.'" y1="'.$top.'" x2="'.$left.'" y2="'.($top + $chartHeight).'"/>'
            .'<line x1="'.$left.'" y1="'.($top + $chartHeight).'" x2="'.($left + $chartWidth).'" y2="'.($top + $chartHeight).'"/>'
            .'</g>'
            .($area ? '<path d="'.$area.'" fill="rgba(180, 97, 57, .16)"/>' : '')
            .($line ? '<path d="'.$line.'" fill="none" stroke="var(--color-paw, #b46139)" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>' : '')
            .'<g fill="var(--color-paw, #b46139)">'.$dots.'</g>'
            .'<g fill="var(--color-bark, #271f1b)" font-family="ui-sans-serif, system-ui, sans-serif" font-size="12">'
            .'<text x="8" y="'.($top + 10).'" transform="rotate(-90 8 '.($top + 10).')">'.e($unit).'</text>'
            .'<text x="'.$left.'" y="'.($height - 10).'">'.$firstYear.'</text>'
            .'<text x="'.($left + $chartWidth - 28).'" y="'.($height - 10).'">'.$lastYear.'</text>'
            .'<text x="'.min($latest['x'] + 8, $width - 96).'" y="'.max($latest['y'] - 8, 16).'" font-weight="700">'.$this->formatWeight($latest['weight']).' '.e($unit).'</text>'
            .'</g>'
            .'</svg>';
    }

    /**
     * @param  iterable<int, array{date: string|DateTimeInterface, weight: int|float|string}>  $entries
     * @return list<array{date: string, time: int, weight: float}>
     */
    private function normalizePoints(iterable $entries): array
    {
        $points = [];

        foreach ($entries as $entry) {
            $date = $entry['date'];
            $weight = $entry['weight'];

            if (! is_numeric($weight)) {
                continue;
            }

            $date = $date instanceof DateTimeInterface
                ? CarbonImmutable::instance($date)
                : CarbonImmutable::parse((string) $date);

            $points[] = [
                'date' => $date->toDateString(),
                'time' => $date->getTimestamp(),
                'weight' => round((float) $weight, 2),
            ];
        }

        usort($points, static fn (array $left, array $right): int => $left['time'] <=> $right['time']);

        return $points;
    }

    private function scale(float|int $value, float|int $min, float|int $max, float|int $targetMin, float|int $targetMax): float
    {
        if ($min === $max) {
            return (float) (($targetMin + $targetMax) / 2);
        }

        return (float) ($targetMin + (($value - $min) / ($max - $min)) * ($targetMax - $targetMin));
    }

    /**
     * @param  list<array{x: float, y: float}>  $points
     */
    private function bezierPath(array $points): string
    {
        $path = sprintf('M %.2f %.2f', $points[0]['x'], $points[0]['y']);

        for ($index = 0; $index < count($points) - 1; $index++) {
            $current = $points[$index];
            $next = $points[$index + 1];
            $previous = $points[$index - 1] ?? $current;
            $following = $points[$index + 2] ?? $next;
            $controlOneX = $current['x'] + ($next['x'] - $previous['x']) / 6;
            $controlOneY = $current['y'] + ($next['y'] - $previous['y']) / 6;
            $controlTwoX = $next['x'] - ($following['x'] - $current['x']) / 6;
            $controlTwoY = $next['y'] - ($following['y'] - $current['y']) / 6;

            $path .= sprintf(
                ' C %.2f %.2f %.2f %.2f %.2f %.2f',
                $controlOneX,
                $controlOneY,
                $controlTwoX,
                $controlTwoY,
                $next['x'],
                $next['y']
            );
        }

        return $path;
    }

    private function formatWeight(float $weight): string
    {
        return rtrim(rtrim(number_format($weight, 2, '.', ''), '0'), '.');
    }
}
