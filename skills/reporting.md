# Reporting Rules

Reports support polymorphic targets:

- `Post`
- `Comment`
- `User`

## Report Fields

- `reporter_id`
- `reportable_type`, `reportable_id`
- `reason`, `details`
- `status`, `resolved_by`, `resolved_at`

## Reason Values

- `spam`
- `harassment`
- `hate_speech`
- `misinformation`
- `nudity`
- `violence`
- `other`

## Status Values

- `pending` (default)
- `reviewed`
- `dismissed`
- `actioned`

## Constraints

- Auth required.
- Cannot report own content/profile.
- One report per user + reportable + reason.
- Duplicate same reason should be idempotent.
- Different reason on same target creates a new report.

## Side Effects

- Reporting does not immediately hide content.
- Reporter does not notify reportee.
- Notify admins when pending reports for same target reach threshold (5).

`ReportService` owns business logic.
`ReportPolicy` owns authorization.
