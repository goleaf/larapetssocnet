# SVG Charts

Use pure server-rendered SVG. No JS chart libraries.

## Weight chart
`ChartService::weightChart(Collection $logs, string $unit = 'kg'): ?string`

- Return `null` when there are no valid weight points.
- Render a single weight point as a labeled dot without a line.
- Normalizes points into `viewBox="0 0 600 200"`.
- Draw a smooth SVG path + circles for multiple points.
- Add axis labels and light grid lines.
- Add dot `<title>` tooltips.
- Use Warm Editorial paw color tokens and responsive width `100%`.
- Static rendering only (no animation).
