---
name: laravel:filesystem-uploads
description: Store and serve files via Storage; set visibility, generate URLs, and handle streaming safely
---

# Filesystem Uploads and URLs

## Laravel 13 Baseline

Use this skill for this app as Laravel 13.9 guidance on PHP 8.4 with Pest 4, PHPUnit 12, Tailwind CSS 4, Livewire 4, SQLite, and the repository-root shared-hosting web surface. Project rules in `AGENTS.md`, Laravel Boost, and local `skills/*.md` guides override generic examples.

Use the Storage facade consistently; abstract away the backend (local, S3, etc.).

## Commands

```
$path = Storage::disk('public')->putFile('avatars', $request->file('avatar'));

// Temporary URLs (S3, etc.)
$url = Storage::disk('s3')->temporaryUrl($path, now()->addMinutes(10));

// Streams
return Storage::disk('backups')->download('db.sql.gz');
```

## Patterns

- Keep user uploads under a dedicated disk with explicit `visibility`
- Avoid assuming local paths; always go through Storage
- For public assets, run `storage:link` and serve via web server / CDN
- Validate mime/types and size limits at upload boundaries

