# SVG Charts

Use pure server-rendered SVG. No JS chart libraries.

## Weight chart
`ChartService::weightChart(Collection $logs, string $unit = 'kg'): ?string`

- Return `null` if fewer than 2 points.
- Normalizes points into `viewBox="0 0 600 200"`.
- Draw polyline + circles.
- Add axis labels and light grid lines.
- Add dot `<title>` tooltips.
- Use emerald line/dots and responsive width `100%`.
- Static rendering only (no animation).
