# Sidebar Widgets

Right sidebar widgets on feed page.

## Rule
- Feed sidebars are bundled lazy Livewire child components under the full-page `pages.feed.index` shell.
- Right-sidebar aggregate data is fetched through `FeedService` from `feed.right-sidebar`.
- Sidebar components own their narrow queries so the center feed stream can render first.
- Keep sidebar queries capped, eager-loaded, and independent from the center feed pagination state.

## Feed Widgets
- Owned pets shortcut
  - Owner stats and pet shortcuts are prepared by `feed.left-sidebar`.
  - Show the current user's avatar, profile counts, and owned pets.
- Suggested users
  - 5 suggested users from `auth()->user()->getSuggestedUsersToFollow(5)`.
  - Show avatar, name, username, follow action.
  - See more link: `/search?type=users`.
- Trending hashtags
  - Top 10 hashtags from `FeedService::trendingHashtags()`.
  - Cache under `feed:trending-hashtags` for 10 minutes, using cache tags when supported and a plain-key fallback when not.
  - Show `#name` and posts count.
  - Links to `/hashtags/{slug}`.
- Upcoming pet birthdays
  - Up to 5 visible pets owned by accepted follows with `birthday_month_day` in the next 7 days.
  - Show pet avatar, name, and days until birthday.
- Joined groups
  - Up to 6 active groups from the viewer's group memberships.
  - Show name, privacy, and member count.

## Loading Order
- `pages.feed.index` renders `feed.stream` immediately.
- `feed.left-sidebar` and `feed.right-sidebar` hydrate lazily after the center stream.
- Sidebar widgets should not dispatch feed pagination, filtering, or polling actions.
