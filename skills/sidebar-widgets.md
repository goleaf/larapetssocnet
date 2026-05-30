# Sidebar Widgets

Right sidebar widgets on feed page.

## Rule
- All right-sidebar widget data is fetched through `FeedController` and `FeedService`.
- Components receive prepared data via props.
- No component performs DB queries.
- The left feed sidebar may use the authenticated user and owned-pet collection already prepared by the controller.

## Feed Widgets
- Owned pets shortcut
  - Owner stats and pet shortcuts are prepared by `FeedController@index`.
  - Show the current user's avatar, profile counts, and owned pets.
- Suggested users
  - 5 suggested users from `auth()->user()->getSuggestedUsersToFollow(5)`.
  - Show avatar, name, username, follow action.
  - See more link: `/search?type=users`.
- Trending hashtags
  - Top 10 hashtags from `Hashtag::trending(10)->get()`.
  - Cache under `feed:trending-hashtags` for 10 minutes.
  - Show `#name` and posts count.
  - Links to `/hashtags/{slug}`.
- Upcoming pet birthdays
  - Up to 5 visible pets owned by accepted follows with `birthday_month_day` in the next 7 days.
  - Show pet avatar, name, and days until birthday.
- Joined groups
  - Up to 6 active groups from the viewer's group memberships.
  - Show name, privacy, and member count.

## Loading Order
- Controller fetches all widget datasets.
- Pass via `compact()` or merged array.
- Widgets make no additional queries.
