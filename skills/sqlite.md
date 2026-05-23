# SQLite

- Keep schema simple and compatible with SQLite limitations.
- Prefer scalar columns over JSON for pivot/state fields.
- Add explicit indexes for high-frequency filters.
- Avoid vendor-specific SQL except for documented schema invariants such as SQLite expression indexes that enforce case-insensitive username uniqueness.
