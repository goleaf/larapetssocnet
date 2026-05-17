---
name: larapetssocnet-performance-guides
description: Use when optimizing feed, profile, search, explore, or widget queries in this app, including eager loading, pagination, counters, SQLite constraints, and query-count testing. Read the matching project guide from the repo's skills folder before changing code.
---

# Larapetssocnet Performance Guides

Use this skill for read-heavy pages and data-access optimizations. Open only the guide files that match the performance work.

Also read the matching project guides when relevant:
- [eager-loading-patterns.md](../../../skills/eager-loading-patterns.md)
- [query-optimization.md](../../../skills/query-optimization.md)
- [relations.md](../../../skills/relations.md)
- [testing.md](../../../skills/testing.md)

## Performance Lookup

- Feed and discovery performance: [feed-architecture.md](../../../skills/feed-architecture.md), [feed-filtering.md](../../../skills/feed-filtering.md), [explore-architecture.md](../../../skills/explore-architecture.md), [trending-algorithm.md](../../../skills/trending-algorithm.md)
- Query loading discipline: [eager-loading-patterns.md](../../../skills/eager-loading-patterns.md), [query-optimization.md](../../../skills/query-optimization.md), [pagination-patterns.md](../../../skills/pagination-patterns.md)
- ORM and persistence constraints: [orm.md](../../../skills/orm.md), [eloquent-patterns.md](../../../skills/eloquent-patterns.md), [relations.md](../../../skills/relations.md), [pivot-models.md](../../../skills/pivot-models.md), [sqlite.md](../../../skills/sqlite.md)
- Counter and observer side effects: [counters.md](../../../skills/counters.md), [post-observer.md](../../../skills/post-observer.md), [events-listeners.md](../../../skills/events-listeners.md)
- Verification patterns: [testing.md](../../../skills/testing.md)

Prefer measured changes over speculative optimization.
