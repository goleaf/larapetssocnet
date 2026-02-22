<?php

namespace App\Services;

use Illuminate\Support\Collection;

class ChartService
{
    /**
     * Generate a pure server-rendered SVG line chart from weight logs.
     * No JS charting library — returns an embeddable SVG string.
     *
     * Each log is expected to have `logged_at` (or `log_date`) + `weight_kg` (or `value`).
     *
     * @return string|null Raw SVG string, or null if fewer than 2 data points
     */
    public function weightChart(Collection $logs, string $unit = 'kg'): ?string
    {
        if ($logs->count() < 2) {
            return null;
        }

        $w = 600;
        $h = 200;
        $pad = ['top' => 20, 'right' => 20, 'bottom' => 40, 'left' => 55];
        $iw = $w - $pad['left'] - $pad['right'];
        $ih = $h - $pad['top'] - $pad['bottom'];

        // Extract values — support both column naming conventions
        $data = $logs->map(function ($log) {
            $date = $log->logged_at ?? $log->log_date ?? $log->created_at;
            $value = (float) ($log->weight_kg ?? $log->value ?? 0);

            return (object) ['date' => $date, 'value' => $value];
        })->filter(fn ($d) => $d->value > 0)->values();

        if ($data->count() < 2) {
            return null;
        }

        $vals = $data->pluck('value');
        $minV = $vals->min();
        $maxV = $vals->max();
        $range = max($maxV - $minV, 0.1);

        // Add 10% padding to value range
        $minV -= $range * 0.1;
        $maxV += $range * 0.1;
        $range = $maxV - $minV;

        $minD = $data->first()->date->timestamp;
        $maxD = $data->last()->date->timestamp;
        $dRange = max($maxD - $minD, 1);

        // Calculate point coordinates
        $points = $data->map(function ($d) use ($minD, $dRange, $iw, $pad, $minV, $range, $ih) {
            $x = $pad['left'] + (($d->date->timestamp - $minD) / $dRange) * $iw;
            $y = ($pad['top'] + $ih) - (($d->value - $minV) / $range) * $ih;

            return (object) ['x' => round($x, 1), 'y' => round($y, 1), 'data' => $d];
        });

        $polyline = $points->map(fn ($p) => "{$p->x},{$p->y}")->implode(' ');

        // Build SVG
        $svg = '<svg viewBox="0 0 '.$w.' '.$h.'" width="100%" height="auto" '
            .'role="img" aria-label="Weight history chart" '
            .'xmlns="http://www.w3.org/2000/svg" class="text-gray-600">';

        // Grid lines + Y-axis labels (5 steps)
        $ySteps = 5;
        for ($i = 0; $i <= $ySteps; $i++) {
            $val = $minV + ($range / $ySteps) * $i;
            $y = ($pad['top'] + $ih) - (($val - $minV) / $range) * $ih;
            $label = number_format($val, 1);

            $svg .= '<line x1="'.$pad['left'].'" y1="'.round($y, 1).'" '
                .'x2="'.($w - $pad['right']).'" y2="'.round($y, 1).'" '
                .'stroke="#e5e7eb" stroke-width="1" />';

            $svg .= '<text x="'.($pad['left'] - 5).'" y="'.round($y + 3, 1).'" '
                .'text-anchor="end" fill="#9ca3af" font-size="10" font-family="sans-serif">'
                .$label.'</text>';
        }

        // X-axis labels (max 6 evenly spaced)
        $maxLabels = min(6, $data->count());
        $step = max(1, (int) floor($data->count() / $maxLabels));

        for ($i = 0; $i < $data->count(); $i += $step) {
            $p = $points[$i];
            $svg .= '<text x="'.$p->x.'" y="'.($h - 5).'" '
                .'text-anchor="middle" fill="#9ca3af" font-size="10" font-family="sans-serif">'
                .$p->data->date->format('M j').'</text>';
        }

        // Polyline
        $svg .= '<polyline points="'.$polyline.'" '
            .'fill="none" stroke="#10b981" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" />';

        // Dots with tooltips
        foreach ($points as $p) {
            $svg .= '<circle cx="'.$p->x.'" cy="'.$p->y.'" r="4" '
                .'fill="#10b981" stroke="white" stroke-width="2">'
                .'<title>'.$p->data->date->format('M j, Y').': '
                .number_format($p->data->value, 1).' '.e($unit).'</title>'
                .'</circle>';
        }

        $svg .= '</svg>';

        return $svg;
    }
}
