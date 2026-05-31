<?php

return [
    'namespace' => env('APP_CACHE_NAMESPACE', 'ps'),

    'version' => env('CACHE_KEY_VERSION', 'v1'),

    // Cache key namespace convention:
    // ps:{version}:{tenant?}:{group}:{resource}:{segment...}
    // Tenant segment is currently reserved for future multi-tenant deployments.
    //
    // Query TTL profile by domain area:
    'ttl' => [
        // Layout + navigation
        'layout.community_stats' => (int) env('CACHE_TTL_LAYOUT_COMMUNITY_STATS', 300),
        'layout.trending_hashtags' => (int) env('CACHE_TTL_LAYOUT_TRENDING_HASHTAGS', 300),
        'layout.upcoming_events' => (int) env('CACHE_TTL_LAYOUT_UPCOMING_EVENTS', 180),
        'layout.active_contests' => (int) env('CACHE_TTL_LAYOUT_ACTIVE_CONTESTS', 300),
        'layout.suggested_users' => (int) env('CACHE_TTL_LAYOUT_SUGGESTED_USERS', 180),
        'layout.user_groups' => (int) env('CACHE_TTL_LAYOUT_USER_GROUPS', 180),

        // Administrative dashboards
        'admin.stats' => (int) env('CACHE_TTL_ADMIN_STATS', 300),

        // Messaging and notifications
        'messaging.unread_messages' => (int) env('CACHE_TTL_MESSAGING_UNREAD_MESSAGES', 60),
        'messaging.unread_notifications' => (int) env('CACHE_TTL_MESSAGING_UNREAD_NOTIFICATIONS', 60),
        'messaging.nav' => (int) env('CACHE_TTL_MESSAGING_NAV', 180),

        // Comments and content
        'comment.insights' => (int) env('CACHE_TTL_COMMENT_INSIGHTS', 360),
        'comment.has_visible_replies' => (int) env('CACHE_TTL_COMMENT_HAS_VISIBLE_REPLIES', 60),
        'comment.viewer_can_pin' => (int) env('CACHE_TTL_COMMENT_VIEWER_CAN_PIN', 60),
        'comment.gif_search' => (int) env('CACHE_TTL_COMMENT_GIF_SEARCH', 600),

        // External and lookup services
        'external.link_preview' => (int) env('CACHE_TTL_EXTERNAL_LINK_PREVIEW', 120),
        'external.gif_search' => (int) env('CACHE_TTL_EXTERNAL_GIF_SEARCH', 420),
        'external.geocode_suggest' => (int) env('CACHE_TTL_EXTERNAL_GEOCODE_SUGGEST', 120),
        'external.geocode_reverse' => (int) env('CACHE_TTL_EXTERNAL_GEOCODE_REVERSE', 300),

        // Permissions and settings
        'permissions.moderation_team' => (int) env('CACHE_TTL_MODERATION_TEAM', 300),
        'permissions.admin_or_moderator' => (int) env('CACHE_TTL_ADMIN_MODERATOR', 120),
        'settings.user_notifications' => (int) env('CACHE_TTL_USER_NOTIFICATION_PREFERENCES', 600),
    ],

    'tags' => [
        'enabled_by_default' => true,
    ],
];
