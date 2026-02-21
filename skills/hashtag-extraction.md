# Hashtag Extraction

Regex: `/#([a-zA-Z0-9_]{1,50})/u`

Flow on create/update:
1. Extract tags from `body`
2. `firstOrCreate` hashtag rows
3. `sync()` to `post_hashtag`
4. Increment/decrement `posts_count` via ORM
