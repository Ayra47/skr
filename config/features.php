<?php

return [
    /*
     * Enable the Communities feature (groups, topics, E2EE posts).
     * Requires community tables to be migrated first.
     */
    'communities_enabled' => (bool) env('FEATURE_COMMUNITIES', false),

    /*
     * Enable unified feed_items cursor pagination.
     * When disabled, Feed.php falls back to the original simplePaginate query.
     * Enable only after feed:backfill-items has been verified.
     */
    'unified_feed_items_enabled' => (bool) env('FEATURE_UNIFIED_FEED_ITEMS', false),

    /*
     * Project encrypted community_posts into feed_items.
     * Disabled by default while rendering/profile/bookmark integrations stay off.
     */
    'community_feed_items_enabled' => (bool) env('FEATURE_COMMUNITY_FEED_ITEMS', false),
];
