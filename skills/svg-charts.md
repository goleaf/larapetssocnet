# SVG Charts

Use pure server-rendered SVG. No JS chart libraries.

## Weight chart
`ChartService::weightChart(Collection $logs): ?string`

- Return `null` if fewer than 2 points.
- Normalize points into `viewBox="0 0 600 200"`.
- Draw polyline + circles.
- Add axis labels and lightweight grid lines.
- Add dot `<title>` tooltips.
- Use emerald line/dots, responsive width 100%.
- Static rendering only (no animation).
