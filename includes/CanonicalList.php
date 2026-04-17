<?php
declare(strict_types=1);

namespace NextPostsBlock;

/**
 * Retrieves and caches the canonical ordered list of published post IDs
 * for a given post type, sorted by the caller's chosen orderby + order.
 *
 * Cache is invalidated automatically via WordPress's last_changed mechanism
 * on the 'posts' cache group (incremented on any post create/update/delete).
 */
final class CanonicalList
{
    private const CACHE_GROUP = 'next-posts-block';
    private const CACHE_TTL = HOUR_IN_SECONDS;

    private const ALLOWED_ORDERBY = ['date', 'title'];
    private const ALLOWED_ORDER = ['ASC', 'DESC'];

    /**
     * @param string $post_type      WordPress post type slug.
     * @param string $orderby        'date' or 'title'.
     * @param string $order          'ASC' or 'DESC'.
     * @param bool   $exclude_sticky When true, sticky posts are removed from the list.
     * @return int[] Ordered list of published post IDs.
     */
    public static function get(
        string $post_type,
        string $orderby = 'date',
        string $order = 'ASC',
        bool $exclude_sticky = false
    ): array {
        if (!post_type_exists($post_type)) {
            return [];
        }

        $orderby = in_array($orderby, self::ALLOWED_ORDERBY, true) ? $orderby : 'date';
        $order = in_array(strtoupper($order), self::ALLOWED_ORDER, true) ? strtoupper($order) : 'ASC';

        $last_changed = wp_cache_get_last_changed('posts');
        $cache_flag = $exclude_sticky ? 'no_sticky' : 'with_sticky';
        $cache_key = "canonical:{$post_type}:{$orderby}:{$order}:{$cache_flag}:{$last_changed}";

        $cached = wp_cache_get($cache_key, self::CACHE_GROUP);
        if ($cached !== false) {
            return $cached;
        }

        $args = [
            'post_type' => $post_type,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'orderby' => $orderby,
            'order' => $order,
            'no_found_rows' => true,
            'suppress_filters' => true,
            'ignore_sticky_posts' => 1,
        ];

        if ($exclude_sticky) {
            $sticky_ids = get_option('sticky_posts', []);
            if (!empty($sticky_ids)) {
                $args['post__not_in'] = array_map('intval', (array) $sticky_ids);
            }
        }

        $query = new \WP_Query($args);

        $ids = array_map('intval', $query->posts);
        wp_cache_set($cache_key, $ids, self::CACHE_GROUP, self::CACHE_TTL);
        return $ids;
    }
}
