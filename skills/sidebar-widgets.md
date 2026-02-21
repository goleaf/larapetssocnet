# Sidebar Widgets

Right sidebar widgets on feed page.

## Rule
- All widget data is fetched in `FeedController`.
- Components receive prepared data via props.
- No component performs DB queries.

## Feed Widgets
- `x-widget-who-to-follow`
  - 4 suggested users from `auth()->user()->getSuggestedUsersToFollow(4)`.
  - Show avatar, name, username, follow action.
  - See more link: `/explore?tab=users`.
- `x-widget-trending-hashtags`
  - Top 6 hashtags from `Hashtag::trending(6)->get()`.
  - Show `#name` and posts count.
  - Links to `/hashtags/{slug}`.
- `x-widget-upcoming-events`
  - Next 2 upcoming events from `Event::upcoming()->limit(2)->get()`.
  - Prefer user attending events if implemented; otherwise fallback to public upcoming.
- `x-widget-active-contests`
  - One active contest from `Contest::active()->first()`.
  - Show title, deadline, entries count.

## Loading Order
- Controller fetches all widget datasets.
- Pass via `compact()` or merged array.
- Widgets make no additional queries.
