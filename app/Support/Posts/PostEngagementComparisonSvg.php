<?php

namespace App\Support\Posts;

class PostEngagementComparisonSvg
{
    /**
     * @param  iterable<int, mixed>  $metrics
     */
    public function render(iterable $metrics): ?string
    {
        $points = $this->normalize($metrics);

        if ($points === []) {
            return null;
        }

        $width = 600;
        $height = 220;
        $left = 70;
        $right = 24;
        $top = 22;
        $bottom = 54;
        $chartWidth = $width - $left - $right;
        $chartHeight = $height - $top - $bottom;
        $baseline = $top + $chartHeight;
        $maxValue = max(1, ...collect($points)->flatMap(fn (array $point): array => [$point['post'], $point['average']])->all());
        $groupWidth = $chartWidth / count($points);
        $barWidth = min(28, max(14, $groupWidth * 0.22));

        $bars = collect($points)
            ->map(function (array $point, int $index) use ($left, $baseline, $chartHeight, $maxValue, $groupWidth, $barWidth): string {
                $center = $left + ($groupWidth * $index) + ($groupWidth / 2);
                $postHeight = $this->barHeight($point['post'], $maxValue, $chartHeight);
                $averageHeight = $this->barHeight($point['average'], $maxValue, $chartHeight);
                $postX = $center - $barWidth - 3;
                $averageX = $center + 3;
                $labelX = $center;

                return '<g>'
                    .'<rect data-series="post" x="'.$this->format($postX).'" y="'.$this->format($baseline - $postHeight).'" width="'.$this->format($barWidth).'" height="'.$this->format($postHeight).'" rx="5" fill="var(--color-paw, #b46139)"><title>This post '.$this->escape($this->formatValue($point['post'])).' '.$this->escape($point['label']).'</title></rect>'
                    .'<rect data-series="average" x="'.$this->format($averageX).'" y="'.$this->format($baseline - $averageHeight).'" width="'.$this->format($barWidth).'" height="'.$this->format($averageHeight).'" rx="5" fill="var(--color-leaf, #54745f)"><title>Average '.$this->escape($this->formatValue($point['average'])).' '.$this->escape($point['label']).'</title></rect>'
                    .'<text x="'.$this->format($labelX).'" y="190" text-anchor="middle">'.$this->escape($point['label']).'</text>'
                    .'</g>';
            })
            ->join('');

        return '<svg xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Post engagement comparison chart" viewBox="0 0 600 220" width="100%" height="220">'
            .'<rect width="600" height="220" rx="12" fill="var(--surface-subtle, #fffaf3)"/>'
            .'<g stroke="rgba(95,74,61,.18)" stroke-width="1">'
            .'<line x1="'.$left.'" y1="'.$baseline.'" x2="'.($width - $right).'" y2="'.$baseline.'"/>'
            .'<line x1="'.$left.'" y1="'.$top.'" x2="'.$left.'" y2="'.$baseline.'"/>'
            .'</g>'
            .'<g font-family="ui-sans-serif, system-ui, sans-serif" font-size="12" fill="var(--color-bark, #271f1b)">'
            .'<text x="'.$left.'" y="16" font-weight="700">This post vs last 10-post average</text>'
            .'<text x="440" y="16"><tspan fill="var(--color-paw, #b46139)">■</tspan> This post</text>'
            .'<text x="524" y="16"><tspan fill="var(--color-leaf, #54745f)">■</tspan> Avg</text>'
            .$bars
            .'</g>'
            .'</svg>';
    }

    /**
     * @param  iterable<int, mixed>  $metrics
     * @return list<array{label: string, post: float, average: float}>
     */
    private function normalize(iterable $metrics): array
    {
        $points = [];

        foreach ($metrics as $metric) {
            if (! is_array($metric)
                || ! array_key_exists('label', $metric)
                || ! array_key_exists('post', $metric)
                || ! array_key_exists('average', $metric)
                || ! is_numeric($metric['post'])
                || ! is_numeric($metric['average'])
            ) {
                continue;
            }

            $points[] = [
                'label' => (string) $metric['label'],
                'post' => max(0, (float) $metric['post']),
                'average' => max(0, (float) $metric['average']),
            ];
        }

        return $points;
    }

    private function barHeight(float $value, float $maxValue, int $chartHeight): float
    {
        if ($value <= 0) {
            return 2.0;
        }

        return max(2, ($value / $maxValue) * $chartHeight);
    }

    private function format(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    private function formatValue(float $value): string
    {
        return floor($value) === $value
            ? number_format($value, 0)
            : number_format($value, 1);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
