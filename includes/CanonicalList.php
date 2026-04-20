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
    private const ALLOWED_STICKY = ['', 'ignore', 'exclude', 'only'];

    /**
     * @param array<string, mixed> $query_attrs  Block `query` attributes bag.
     * @return int[] Ordered list of published post IDs.
     */
    public static function build(array $query_attrs): array
    {
        $normalized = self::normalize($query_attrs);
        if ($normalized === null) {
            return [];
        }

        $last_changed = wp_cache_get_last_changed('posts');
        $cache_key = 'canonical:' . md5(serialize($normalized)) . ':' . $last_changed;

        $cached = wp_cache_get($cache_key, self::CACHE_GROUP);
        if ($cached !== false) {
            return $cached;
        }

        $args = self::build_query_args($normalized);
        $query = new \WP_Query($args);
        $ids = array_map('intval', $query->posts);

        $ids = self::apply_sticky_mode($ids, $normalized['sticky'], $args);

        wp_cache_set($cache_key, $ids, self::CACHE_GROUP, self::CACHE_TTL);
        return $ids;
    }

    /**
     * @param array<string, mixed> $query_attrs
     * @return array<string, mixed>|null Null if postType invalid.
     */
    private static function normalize(array $query_attrs): ?array
    {
        $post_type = (string) ($query_attrs['postType'] ?? 'post');
        if (!post_type_exists($post_type)) {
            return null;
        }

        $orderby_raw = (string) ($query_attrs['orderBy'] ?? 'date');
        $order_raw = strtoupper((string) ($query_attrs['order'] ?? 'ASC'));
        $sticky_raw = (string) ($query_attrs['sticky'] ?? '');

        $search = trim((string) ($query_attrs['search'] ?? ''));

        $author_raw = (string) ($query_attrs['author'] ?? '');
        $author = array_values(array_filter(
            array_map('intval', $author_raw === '' ? [] : explode(',', $author_raw)),
            static fn($id) => $id > 0
        ));

        $tax_query_raw = $query_attrs['taxQuery'] ?? [];
        $tax_query = [];
        if (is_array($tax_query_raw)) {
            foreach ($tax_query_raw as $taxonomy => $term_ids) {
                if (!taxonomy_exists((string) $taxonomy)) {
                    continue;
                }
                $ids = array_values(array_filter(
                    array_map('intval', (array) $term_ids),
                    static fn($id) => $id > 0
                ));
                if (!empty($ids)) {
                    $tax_query[(string) $taxonomy] = $ids;
                }
            }
        }

        return [
            'postType' => $post_type,
            'orderBy'  => in_array($orderby_raw, self::ALLOWED_ORDERBY, true) ? $orderby_raw : 'date',
            'order'    => in_array($order_raw, self::ALLOWED_ORDER, true) ? $order_raw : 'ASC',
            'sticky'   => in_array($sticky_raw, self::ALLOWED_STICKY, true) ? $sticky_raw : '',
            'taxQuery' => $tax_query,
            'author'   => $author,
            'search'   => $search,
        ];
    }

    /**
     * @param array<string, mixed> $n  Normalized attrs.
     * @return array<string, mixed> WP_Query args.
     */
    private static function build_query_args(array $n): array
    {
        $args = [
            'post_type'           => $n['postType'],
            'post_status'         => 'publish',
            'posts_per_page'      => -1,
            'fields'              => 'ids',
            'orderby'             => $n['orderBy'],
            'order'               => $n['order'],
            'no_found_rows'       => true,
            'suppress_filters'    => true,
            'ignore_sticky_posts' => 1,
        ];

        if (!empty($n['author'])) {
            $args['author__in'] = $n['author'];
        }

        if ($n['search'] !== '') {
            $args['s'] = $n['search'];
        }

        if (!empty($n['taxQuery'])) {
            $clauses = [];
            foreach ($n['taxQuery'] as $taxonomy => $term_ids) {
                $clauses[] = [
                    'taxonomy' => $taxonomy,
                    'field'    => 'term_id',
                    'terms'    => $term_ids,
                    'operator' => 'IN',
                ];
            }
            if (count($clauses) > 1) {
                $clauses['relation'] = 'AND';
            }
            $args['tax_query'] = $clauses;
        }

        return $args;
    }

    /**
     * @param int[] $ids
     * @param string $mode
     * @param array<string, mixed> $args
     * @return int[]
     */
    private static function apply_sticky_mode(array $ids, string $mode, array $args): array
    {
        if ($mode === 'ignore' || empty($ids)) {
            return $ids;
        }

        $sticky_option = array_map('intval', (array) get_option('sticky_posts', []));

        if ($mode === '') {
            if (empty($sticky_option)) {
                return $ids;
            }
            $matching_stickies = array_values(array_intersect($ids, $sticky_option));
            $non_stickies      = array_values(array_diff($ids, $matching_stickies));
            return array_merge($matching_stickies, $non_stickies);
        }

        if ($mode === 'exclude') {
            if (empty($sticky_option)) {
                return $ids;
            }
            return array_values(array_diff($ids, $sticky_option));
        }

        if ($mode === 'only') {
            if (empty($sticky_option)) {
                return [];
            }
            return array_values(array_intersect($ids, $sticky_option));
        }

        return $ids;
    }

}
