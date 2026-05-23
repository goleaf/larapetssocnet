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

Profile reports must use profile-specific reasons:

- `profile_impersonation` — Impersonating another person or pet
- `profile_fake_or_misleading` — Fake or misleading profile
- `profile_inappropriate_content` — Inappropriate profile content
- `profile_spam_account` — Spam account
- `profile_harmful_content` — Harmful or dangerous content

Post and comment reports keep the general content/moderation reasons:

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
- Reporting a profile does not block it; the reported profile remains visible unless a separate visibility, block, or moderation rule denies access.
- Reporter does not notify reportee.
- Profile reports notify the moderation team immediately.
- Notify admins when pending reports for same target reach threshold (5).

`ReportService` owns business logic.
`ReportPolicy` owns authorization.
